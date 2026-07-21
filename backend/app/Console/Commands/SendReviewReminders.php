<?php

namespace App\Console\Commands;

use App\Models\Sentence;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Daily review reminder: SRS only works when learners come back on schedule.
 * Emails everyone who has due reviews, hasn't practiced today, and hasn't
 * opted out (profile toggle → users.review_emails). Scheduled HOURLY in
 * routes/console.php; each learner is mailed in the hour that matches the
 * practice time they picked at intake ("when will you practice?"), in their
 * own timezone - an appointment they chose, not our 17:00. Safe to run
 * manually. Uses whatever MAIL_* transport is configured (the default `log`
 * driver makes this a no-op in dev).
 *
 * The mail leads with a REAL due sentence rather than a count. The first
 * version said "N sentences are ready for review" in the subject, the
 * preheader and the opening line - three copies of one sentence, no Finnish
 * anywhere, and a number that grows the longer someone stays away, so the
 * message read as accumulating debt exactly when motivation was lowest. It
 * converted about 8%. Leading with a sentence they are about to forget makes
 * the mail a micro-lesson that pays off even unopened, and the count moves
 * below the fold where it informs instead of intimidates.
 */
class SendReviewReminders extends Command
{
    protected $signature = 'reminders:send
        {--dry-run : List recipients without sending}
        {--all-hours : Ignore the per-user practice hour (for manual runs)}';

    protected $description = 'Email learners whose reviews are due and who have not practiced today';

    /** Local send hour per intake slot; unset/legacy accounts keep 17:00. */
    private const SLOT_HOURS = ['morning' => 8, 'lunch' => 12, 'evening' => 18];

    private const DEFAULT_HOUR = 17;

    /** Cards a returning learner is actually asked for, and seconds each. */
    private const START_WITH = 5;

    private const SECONDS_PER_CARD = 30;

    /** Mirrors SessionController::SESSION_MIN - the shortest session it builds. */
    private const SESSION_MIN = 3;

    public function handle(): int
    {
        $candidates = User::where('review_emails', true)
            ->whereHas('progress', fn ($q) => $q->where('next_review_at', '<=', now()))
            ->get();

        // Both gates run per-user in PHP because both are questions about the
        // learner's OWN clock. "Practised today" used to be a SQL comparison
        // against today() (Europe/Helsinki) while last_active_date is written
        // in the learner's zone - for the ~20% of learners outside Finland
        // those are different dates, so the gate misfired in both directions.
        $users = $candidates->filter(function (User $user) {
            $today = $user->localToday();

            if ($user->last_active_date !== null
                && $user->last_active_date->format('Y-m-d') >= $today->format('Y-m-d')) {
                return false; // already practised on their own calendar day
            }

            if ($this->option('all-hours')) {
                return true;
            }

            $slot = $user->preferences['practice_time'] ?? null;
            $hour = self::SLOT_HOURS[$slot] ?? self::DEFAULT_HOUR;

            return now($user->timezone ?: config('app.timezone'))->hour === $hour;
        })->values();

        $dryRun = $this->option('dry-run');
        $failed = 0;

        foreach ($users as $user) {
            // Settle the streak BEFORE quoting it - but never on a dry run,
            // which must stay read-only. syncStreak() only ever ran on the
            // login/me paths, so someone who stopped opening the app kept the
            // streak they had when they left, and this mail told them it was
            // "on the line" days after it had already broken. Lapsed learners
            // are the entire audience here, so the one piece of urgency in the
            // message was reliably false for the people most likely to act.
            if (! $dryRun) {
                $user->syncStreak();
                $user->refresh();
            }

            $dueQuery = $user->progress()->where('next_review_at', '<=', now());
            $due = (clone $dueQuery)->count();
            $lead = (clone $dueQuery)->with('sentence')->orderBy('next_review_at')->first()?->sentence;

            // Compared as bare calendar dates on purpose: last_active_date is
            // a date cast (app zone) while localToday() carries the learner's
            // zone, so diffing the instants would round the offset into a
            // phantom extra day for anyone outside Helsinki.
            $away = $user->last_active_date === null ? null : (int) Carbon::parse(
                $user->last_active_date->format('Y-m-d')
            )->diffInDays(Carbon::parse($user->localToday()->format('Y-m-d')), true);

            if ($dryRun) {
                $this->line("{$user->email}: {$due} due, away {$away}, lead: ".($lead?->finnish_text ?? '-'));

                continue;
            }

            // One learner's failure must not cost everyone else their reminder.
            // Unwrapped, a single throw - a dead SMTP host, an address the
            // server rejects, a transient timeout - aborted the whole run at
            // whatever learner it reached, and the cron swallowed it: no mail,
            // no "Processed N", nothing to notice. The batch is the product
            // here, so a bad address is logged and skipped, never fatal.
            try {
                Mail::send('emails.branded', $this->payload($user, $lead, $due, $away), function ($mail) use ($user, $lead) {
                    $mail->to($user->email)->subject($this->subject($lead));
                });
            } catch (\Throwable $e) {
                $failed++;
                Log::error('reminders:send could not mail a learner', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("{$user->email}: send failed - {$e->getMessage()}");
            }
        }

        // A dry run sends nothing, so it must not claim a send count.
        if ($dryRun) {
            $this->info("Processed {$users->count()} learner(s).");

            return self::SUCCESS;
        }

        $sent = $users->count() - $failed;
        $this->info("Processed {$users->count()} learner(s); {$sent} sent, {$failed} failed.");

        // Non-zero tells the scheduler something is wrong even though the run
        // completed, so a systematically broken transport surfaces in cron mail
        // instead of looking like a clean nightly send.
        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The subject carries the Finnish itself. A learner scanning an inbox can
     * answer "do I still know this?" without opening anything, which is the
     * hook: the question is about them, not about our queue depth.
     */
    private function subject(?Sentence $lead): string
    {
        if ($lead === null || trim((string) $lead->finnish_text) === '') {
            return 'Muistatko vielä? Your Finnish is waiting';
        }

        return 'Muistatko vielä? · "'.$lead->finnish_text.'"';
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user, ?Sentence $lead, int $due, ?int $away): array
    {
        // Floored at the session minimum SessionController will actually build
        // (SESSION_MIN): with two reviews due it still serves three cards,
        // topping up with new sentences, so promising "2" would under-sell the
        // ask by one. The button has to name the session the learner gets.
        $cards = max(self::SESSION_MIN, min($due, self::START_WITH));
        $minutes = max(1, (int) round($cards * self::SECONDS_PER_CARD / 60));
        $noun = $cards === 1 ? 'sentence' : 'sentences';

        $intro = [];

        if ($lead !== null && trim((string) $lead->finnish_text) !== '') {
            // The sentence is the payload. Big, quiet, and answerable in the
            // inbox - the translation sits underneath so the mail teaches even
            // if the button is never pressed.
            $intro[] = '<span style="display:block; font-size:22px; line-height:1.4; font-weight:700; color:#29241d; padding:6px 0 2px;">'
                .e($lead->finnish_text).'</span>';

            if (trim((string) $lead->english_text) !== '') {
                $intro[] = '<span style="display:block; font-size:15px; color:#9a8f7d; padding-bottom:6px;">'
                    .e($lead->english_text).'</span>';
            }
        }

        $intro[] = $this->nudge($due, $away);

        // Only after syncStreak() has settled it. A surviving streak now means
        // the learner really did practise yesterday, so "on the line" is
        // literally true - which is the only condition under which it belongs
        // in the mail at all.
        if ($user->streak > 0) {
            $intro[] = '<span style="color:#b45f0d; font-weight:600;">Your '.$user->streak
                .'-day streak is on the line.</span>';
        }

        return [
            'vaino' => 'vaino-loyly.png',
            'title' => $this->title($user, $away),
            'preheader' => $this->preheader($lead, $away),
            'intro' => $intro,
            // ?size carries the promise into the app: the button names a
            // number of sentences, so the session that opens has to be that
            // long. Without it the link built the learner's full daily goal
            // and the mail was quietly lying about the ask.
            'actionUrl' => rtrim(config('services.stripe.frontend_url') ?: config('app.url'), '/')."/session?size={$cards}",
            'actionText' => "Review {$cards} {$noun} · about {$minutes} min",
            'outro' => [],
            'footerNote' => 'Too much steam? Turn these reminders off any time in your profile.',
        ];
    }

    private function title(User $user, ?int $away): string
    {
        return match (true) {
            $away === null, $away <= 1 => 'Moi '.$user->name.', this one is due today',
            $away <= 3 => 'Moi '.$user->name.', still got it?',
            default => 'Moi '.$user->name.', pick up where you left off',
        };
    }

    /**
     * The inbox preview line. It used to repeat the subject verbatim, which
     * wasted the only other thing a learner reads before deciding to open.
     */
    private function preheader(?Sentence $lead, ?int $away): string
    {
        $english = trim((string) ($lead->english_text ?? ''));
        $tail = $english === '' ? 'Can you still say it?' : "Can you still say \"{$english}\"?";

        return match (true) {
            $away === null, $away <= 1 => $tail,
            $away <= 3 => "It has been {$away} days. ".$tail,
            default => "It has been {$away} days - start with five sentences. ".$tail,
        };
    }

    /**
     * The count, demoted. Long absences are never quoted in full: someone
     * back after a week does not need to be told they owe 60 reviews, they
     * need to be told five is enough to start.
     */
    private function nudge(int $due, ?int $away): string
    {
        $why = $due <= 1
            ? 'Reviewing a sentence right when it comes due is what moves it into long-term memory.'
            : 'Reviewing right when a sentence comes due is what moves it into long-term memory.';

        if ($due <= self::START_WITH) {
            return $why;
        }

        // A long absence is never quantified. Someone back after a week does
        // not need to hear they owe 63 reviews - the number is the reason they
        // did not come back, and five is the only figure that helps them.
        return ($away !== null && $away > 3)
            ? "More are waiting whenever you want them - five is a real session. {$why}"
            : "{$due} are ready in total, but five is a real session. {$why}";
    }
}
