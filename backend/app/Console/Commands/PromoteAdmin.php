<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Grants (or revokes) admin access. CLI-only on purpose: there is no way to
 * become an admin through the web app.
 */
class PromoteAdmin extends Command
{
    protected $signature = 'user:promote {email} {--revoke : take admin away instead}';

    protected $description = 'Make a user an admin (or revoke with --revoke)';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error("No user with email {$this->argument('email')}.");

            return self::FAILURE;
        }

        // forceFill: is_admin is intentionally not mass-assignable (see User).
        $user->forceFill(['is_admin' => ! $this->option('revoke')])->save();
        $this->info("{$user->email} is ".($user->is_admin ? 'now an admin.' : 'no longer an admin.'));

        return self::SUCCESS;
    }
}
