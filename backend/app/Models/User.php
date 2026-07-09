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
        'last_active_date',
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
     * Reset the streak if the user skipped a whole day.
     */
    public function syncStreak(): void
    {
        if ($this->streak > 0
            && ($this->last_active_date === null || $this->last_active_date->lt(today()->subDay()))) {
            $this->update(['streak' => 0]);
        }
    }
}
