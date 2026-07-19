<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Grants (or revokes) recording-studio access - the /record page where a
 * native speaker replaces TTS audio with their own voice. CLI-only on
 * purpose, same as admin promotion.
 */
class PromoteRecorder extends Command
{
    protected $signature = 'user:recorder {email} {--revoke : take recording rights away instead}';

    protected $description = 'Give a user recording-studio access (or revoke with --revoke)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        // forceFill: is_recorder is intentionally not mass-assignable (see User).
        $user->forceFill(['is_recorder' => ! $this->option('revoke')])->save();
        $this->info("{$user->email} ".($user->is_recorder ? 'can now record at /record.' : 'no longer has recording rights.'));

        return self::SUCCESS;
    }
}
