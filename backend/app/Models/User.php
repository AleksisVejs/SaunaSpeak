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
        ];
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

        if ($this->last_active_date->gte(today()->subDay())) {
            return; // active today or yesterday - streak intact
        }

        // Missed exactly one day with a freeze in the bank: spend it. Bumping
        // last_active_date to yesterday lets today's session continue the streak.
        if ($this->streak_freezes > 0 && $this->last_active_date->isSameDay(today()->subDays(2))) {
            $this->update([
                'streak_freezes' => $this->streak_freezes - 1,
                'last_active_date' => today()->subDay(),
            ]);

            return;
        }

        $this->update(['streak' => 0]);
    }
}
