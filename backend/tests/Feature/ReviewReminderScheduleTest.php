<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Pattern;
use App\Models\Sentence;
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function learnerWithDueReview(array $overrides = []): User
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
        $sentence = $lesson->sentences()->create(['finnish_text' => uniqid(), 'english_text' => 'x']);

        $user->progress()->create([
            'sentence_id' => $sentence->id,
            'status' => UserProgress::STATUS_LEARNING,
            'ease' => 2.5,
            'interval_days' => 1,
            'next_review_at' => now()->subHour(),
        ]);

        return $user;
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
}
