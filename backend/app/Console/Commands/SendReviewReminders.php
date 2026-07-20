<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
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

    public function handle(): int
    {
        $users = User::where('review_emails', true)
            ->whereHas('progress', fn ($q) => $q->where('next_review_at', '<=', now()))
            ->where(function ($q) {
                $q->whereNull('last_active_date')->orWhere('last_active_date', '<', today());
            })
            ->get();

        // The hour gate: running hourly, each learner matches exactly once a
        // day - when their own clock strikes their chosen practice hour.
        if (! $this->option('all-hours')) {
            $users = $users->filter(function (User $user) {
                $slot = $user->preferences['practice_time'] ?? null;
                $hour = self::SLOT_HOURS[$slot] ?? self::DEFAULT_HOUR;

                return now($user->timezone ?: config('app.timezone'))->hour === $hour;
            })->values();
        }

        foreach ($users as $user) {
            $due = $user->progress()->where('next_review_at', '<=', now())->count();
            $streakNote = $user->streak > 0
                ? " Your {$user->streak}-day streak is on the line!"
                : '';

            if ($this->option('dry-run')) {
                $this->line("{$user->email}: {$due} due{$streakNote}");

                continue;
            }

            $subject = "🔥 {$due} Finnish ".($due === 1 ? 'sentence' : 'sentences').' ready for review';

            Mail::send('emails.branded', [
                'vaino' => 'vaino-loyly.png',
                'title' => 'Moi '.$user->name.', the sauna is hot!',
                'preheader' => "{$due} ".($due === 1 ? 'sentence is' : 'sentences are').' ready for review.',
                'intro' => [
                    "{$due} ".($due === 1 ? 'sentence is' : 'sentences are')
                    .' ready for review. Reviewing right when a sentence is due is what locks it into long-term memory.'.$streakNote,
                ],
                'actionUrl' => rtrim(config('services.stripe.frontend_url') ?: config('app.url'), '/').'/session',
                'actionText' => 'Hop in the sauna',
                'outro' => [],
                'footerNote' => 'Too much steam? Turn these reminders off any time in your profile.',
            ], fn ($mail) => $mail->to($user->email)->subject($subject));
        }

        $this->info("Processed {$users->count()} learner(s).");

        return self::SUCCESS;
    }
}
