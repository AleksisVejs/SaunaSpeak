<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user, per-day chat message counter. Feeds the weekly insights
 * ("X chat messages this week") without ever storing what was said.
 */
class ChatDay extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'date', 'messages'];

    /** Count one sent message for today. Upsert-increment, race-tolerant. */
    public static function bump(int $userId): void
    {
        $updated = static::where('user_id', $userId)
            ->where('date', today()->toDateString())
            ->increment('messages');

        if ($updated === 0) {
            try {
                static::create(['user_id' => $userId, 'date' => today()->toDateString(), 'messages' => 1]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Two first messages raced; the loser just increments.
                static::where('user_id', $userId)
                    ->where('date', today()->toDateString())
                    ->increment('messages');
            }
        }
    }
}
