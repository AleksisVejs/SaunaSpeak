<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'xp',
        'streak',
        'streak_freezes',
        'last_active_date',
        'checkpoints',
        'preferences',
        'timezone',
        'review_emails',
        'stripe_customer_id',
        'stripe_subscription_id',
        'premium_until',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_active_date' => 'date:Y-m-d',
            'checkpoints' => 'array',
            'preferences' => 'array',
            'premium_until' => 'datetime',
            'is_admin' => 'boolean',
            'review_emails' => 'boolean',
        ];
    }

    /**
     * "Today" on the learner's own clock (IANA zone captured from the browser;
     * falls back to the app timezone). Streaks and daily bonuses key off this,
     * so a session at 23:30 in Helsinki isn't "tomorrow" on a UTC server.
     */
    public function localToday(): \Illuminate\Support\Carbon
    {
        try {
            return now($this->timezone ?: config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            return today(); // junk timezone string from a client - ignore it
        }
    }

    /**
     * Use our branded verification mail. Deliberately NOT implementing the
     * MustVerifyEmail interface: verification is encouraged, never blocking
     * (a broken mail transport must not lock learners out of the app).
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmail);
    }

    /** Branded password-reset mail (link lands on the SPA's reset page). */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPassword($token));
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UserProgress::class);
    }

    public function words(): HasMany
    {
        return $this->hasMany(UserWord::class);
    }

    /**
     * Löyly+ access. While billing is unconfigured (no STRIPE_SECRET), every
     * feature is open - flip the paywall on by adding the Stripe keys.
     */
    public function isPremium(): bool
    {
        if (! config('services.stripe.secret')) {
            return true;
        }

        // +2 days grace so access never flickers while a renewal settles;
        // premium_until itself stays the honest period-end date for display.
        return $this->premium_until !== null && $this->premium_until->copy()->addDays(2)->isFuture();
    }

    /**
     * Reset the streak if the user skipped a whole day - unless a streak
     * freeze covers exactly one missed day, in which case it's consumed
     * silently and the streak survives.
     */
    public function syncStreak(): void
    {
        if ($this->streak === 0 || $this->last_active_date === null) {
            if ($this->streak > 0) {
                $this->update(['streak' => 0]);
            }

            return;
        }

        $today = $this->localToday();

        // Calendar-date comparison (not instants): last_active_date is a bare
        // date while $today carries the user's zone, so comparing timestamps
        // would drift by the UTC offset.
        if ($this->last_active_date->format('Y-m-d') >= $today->copy()->subDay()->format('Y-m-d')) {
            return; // active today or yesterday - streak intact
        }

        // Missed exactly one day with a freeze in the bank: spend it. Bumping
        // last_active_date to yesterday lets today's session continue the streak.
        if ($this->streak_freezes > 0 && $this->last_active_date->isSameDay($today->copy()->subDays(2))) {
            $this->update([
                'streak_freezes' => $this->streak_freezes - 1,
                'last_active_date' => $today->copy()->subDay(),
            ]);

            return;
        }

        $this->update(['streak' => 0]);
    }
}
