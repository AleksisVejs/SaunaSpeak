<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Daily review reminder: SRS only works when learners come back on schedule.
 * Emails everyone who has due reviews and hasn't practiced today. Scheduled
 * in routes/console.php; safe to run manually. Uses whatever MAIL_* transport
 * is configured (the default `log` driver makes this a no-op in dev).
 */
class SendReviewReminders extends Command
{
    protected $signature = 'reminders:send {--dry-run : List recipients without sending}';

    protected $description = 'Email learners whose reviews are due and who have not practiced today';

    public function handle(): int
    {
        $users = User::whereHas('progress', fn ($q) => $q->where('next_review_at', '<=', now()))
            ->where(function ($q) {
                $q->whereNull('last_active_date')->orWhere('last_active_date', '<', today());
            })
            ->get();

        foreach ($users as $user) {
            $due = $user->progress()->where('next_review_at', '<=', now())->count();
            $freezeNote = $user->streak > 0
                ? " Your {$user->streak}-day streak is on the line!"
                : '';

            if ($this->option('dry-run')) {
                $this->line("{$user->email}: {$due} due{$freezeNote}");

                continue;
            }

            Mail::raw(
                "Moi {$user->name}!\n\n"
                ."{$due} ".($due === 1 ? 'sentence is' : 'sentences are')." ready for review - "
                ."reviewing right when they're due is what locks them into long-term memory.{$freezeNote}\n\n"
                ."Hop in the sauna: ".config('app.url')."/session\n\n"
                ."- Väinö 🧖",
                fn ($mail) => $mail->to($user->email)->subject(
                    "🔥 {$due} Finnish ".($due === 1 ? 'sentence' : 'sentences').' ready for review'
                )
            );
        }

        $this->info("Processed {$users->count()} learner(s).");

        return self::SUCCESS;
    }
}
