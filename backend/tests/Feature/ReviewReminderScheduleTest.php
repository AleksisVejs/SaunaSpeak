<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Pattern;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The reminder scheduler runs hourly; the command itself decides who is
 * mailed by matching each learner's LOCAL clock against the practice hour
 * they picked at intake (morning 08, lunch 12, evening 18 - legacy accounts
 * default to 17). These tests pin that gate.
 */
class ReviewReminderScheduleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every test freezes the clock; fixtures are built at this instant so a
     * due review is due no matter what the wall clock says. Without this the
     * helper's next_review_at was pinned to the REAL time, which sits after
     * the 05:00 UTC slot for most of the working day - the morning-slot case
     * then failed purely because of when the suite happened to run.
     */
    private const DAY_START = '2026-07-21 00:00';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse(self::DAY_START, 'UTC'));
        // Array transport so the assertions can read the rendered mail; the
        // command calls Mail::send() with a view, not a Mailable, so there is
        // no class for Mail::fake()'s assertions to match on.
        config(['mail.default' => 'array']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function learnerWithDueReview(array $overrides = [], array $sentenceAttrs = []): User
    {
        $user = User::create(array_merge([
            'name' => 'Testi',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'timezone' => 'Europe/Helsinki',
            'review_emails' => true,
        ], $overrides));

        $pattern = Pattern::create(['title' => 'p', 'summary' => 's', 'examples' => [], 'order_index' => 1]);
        $lesson = Lesson::create(['title' => uniqid(), 'level' => 'A1', 'order_index' => 1, 'pattern_id' => $pattern->id]);
        $sentence = $lesson->sentences()->create(array_merge([
            'finnish_text' => uniqid(),
            'english_text' => 'x',
        ], $sentenceAttrs));

        $user->progress()->create([
            'sentence_id' => $sentence->id,
            'status' => UserProgress::STATUS_LEARNING,
            'ease' => 2.5,
            'interval_days' => 1,
            'next_review_at' => now()->subHour(),
        ]);

        return $user;
    }

    /** Subject + HTML body of every mail the array transport captured. */
    private function sentMail(): array
    {
        return app('mailer')->getSymfonyTransport()->messages()
            ->map(fn ($sent) => [
                'subject' => $sent->getOriginalMessage()->getSubject(),
                'body' => $sent->getOriginalMessage()->getHtmlBody(),
            ])
            ->all();
    }

    public function test_reminder_fires_only_at_the_learners_chosen_local_hour(): void
    {
        $this->learnerWithDueReview(['preferences' => ['practice_time' => 'morning']]);

        // 08:00 in Helsinki (05:00 UTC in July, UTC+3): the morning slot.
        Carbon::setTestNow(Carbon::parse('2026-07-21 05:00', 'UTC'));
        $this->artisan('reminders:send', ['--dry-run' => true])
            ->expectsOutputToContain('Processed 1');

        // 15:00 local: not their hour - nobody mailed.
        Carbon::setTestNow(Carbon::parse('2026-07-21 12:00', 'UTC'));
        $this->artisan('reminders:send', ['--dry-run' => true])
            ->expectsOutputToContain('Processed 0');

        // Manual catch-all runs skip the gate.
        $this->artisan('reminders:send', ['--dry-run' => true, '--all-hours' => true])
            ->expectsOutputToContain('Processed 1');
    }

    public function test_accounts_without_a_practice_time_keep_the_17_legacy_hour(): void
    {
        $this->learnerWithDueReview();

        // 17:00 Helsinki (14:00 UTC in July).
        Carbon::setTestNow(Carbon::parse('2026-07-21 14:00', 'UTC'));
        $this->artisan('reminders:send', ['--dry-run' => true])
            ->expectsOutputToContain('Processed 1');

        Carbon::setTestNow(Carbon::parse('2026-07-21 05:00', 'UTC'));
        $this->artisan('reminders:send', ['--dry-run' => true])
            ->expectsOutputToContain('Processed 0');
    }

    /**
     * The bug this mail shipped with: syncStreak() only ran on the login and
     * /me paths, so a learner who stopped opening the app kept the streak they
     * had when they left - and the reminder quoted it back as "on the line"
     * days after it had broken. Lapsed learners are the whole audience, so the
     * only urgency in the message was false for exactly the people it targeted.
     */
    public function test_a_streak_that_already_broke_is_settled_and_never_claimed(): void
    {
        $user = $this->learnerWithDueReview([
            'streak' => 3,
            'last_active_date' => '2026-07-17', // four days cold
        ]);

        $this->artisan('reminders:send', ['--all-hours' => true])->assertSuccessful();

        $this->assertSame(0, $user->fresh()->streak, 'the dead streak should have been settled');
        $this->assertStringNotContainsString('streak is on the line', $this->sentMail()[0]['body']);
    }

    public function test_a_live_streak_is_still_claimed(): void
    {
        $this->learnerWithDueReview([
            'streak' => 3,
            'last_active_date' => '2026-07-20', // practised yesterday
        ]);

        $this->artisan('reminders:send', ['--all-hours' => true])->assertSuccessful();

        $this->assertStringContainsString('3-day streak is on the line', $this->sentMail()[0]['body']);
    }

    public function test_the_subject_leads_with_the_finnish_sentence_not_a_count(): void
    {
        $this->learnerWithDueReview([], [
            'finnish_text' => 'Mä meen kauppaan',
            'english_text' => "I'm going to the shop",
        ]);

        $this->artisan('reminders:send', ['--all-hours' => true])->assertSuccessful();

        $mail = $this->sentMail()[0];
        $this->assertStringContainsString('Mä meen kauppaan', $mail['subject']);
        $this->assertStringContainsString('Mä meen kauppaan', $mail['body']);
        $this->assertStringContainsString('I&#039;m going to the shop', $mail['body']);
    }

    /**
     * The button names a number of sentences, so the link has to open a
     * session of exactly that length - otherwise the mail promises five and
     * the app serves the learner's full daily goal of eight.
     */
    public function test_the_cta_link_asks_for_the_session_length_it_promises(): void
    {
        $user = $this->learnerWithDueReview();

        // Nine more due on the SAME learner's lesson: well past the five we ask
        // for, so the promise is capped by START_WITH rather than by supply.
        $lesson = $user->progress()->with('sentence')->first()->sentence->lesson;
        for ($i = 0; $i < 9; $i++) {
            $sentence = $lesson->sentences()->create(['finnish_text' => uniqid(), 'english_text' => 'x']);
            $user->progress()->create([
                'sentence_id' => $sentence->id,
                'status' => UserProgress::STATUS_LEARNING,
                'ease' => 2.5,
                'interval_days' => 1,
                'next_review_at' => now()->subHour(),
            ]);
        }

        $this->artisan('reminders:send', ['--all-hours' => true])->assertSuccessful();

        $body = $this->sentMail()[0]['body'];
        $this->assertStringContainsString('Review 5 sentences', $body);
        $this->assertStringContainsString('/session?size=5', $body);
    }

    /**
     * SessionController never builds a session shorter than SESSION_MIN, so a
     * learner with two reviews due still gets three cards. Promising "2" would
     * under-sell the ask, and "1 sentences" would read badly.
     */
    public function test_the_promise_never_drops_below_the_shortest_session_built(): void
    {
        $this->learnerWithDueReview(); // exactly one due

        $this->artisan('reminders:send', ['--all-hours' => true])->assertSuccessful();

        $body = $this->sentMail()[0]['body'];
        $this->assertStringContainsString('Review 3 sentences', $body);
        $this->assertStringContainsString('/session?size=3', $body);
    }

    /**
     * last_active_date is written in the learner's own zone; the "practised
     * today" gate used to compare it against today() in Europe/Helsinki. For
     * the ~20% of learners outside Finland those are different dates, so a
     * learner who had just practised got mailed anyway.
     */
    public function test_practised_today_is_judged_on_the_learners_own_calendar(): void
    {
        // 05:00 UTC = 08:00 Jul 21 in Helsinki, but still 22:00 Jul 20 in LA.
        Carbon::setTestNow(Carbon::parse('2026-07-21 05:00', 'UTC'));

        $this->learnerWithDueReview([
            'timezone' => 'America/Los_Angeles',
            'last_active_date' => '2026-07-20', // their today - already done
        ]);

        $this->artisan('reminders:send', ['--dry-run' => true, '--all-hours' => true])
            ->expectsOutputToContain('Processed 0');
    }
}
