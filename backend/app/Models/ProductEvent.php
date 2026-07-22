<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A sparse first-party funnel milestone; never stores learner content. */
class ProductEvent extends Model
{
    public const FREE_SITUATION_OFFERED = 'free_situation_offered';
    public const FREE_SITUATION_OPENED = 'free_situation_opened';
    public const FREE_SITUATION_STARTED = 'free_situation_started';
    public const FREE_SITUATION_COMPLETED = 'free_situation_completed';
    public const FREE_SITUATION_UPSELL_CLICKED = 'free_situation_upsell_clicked';
    public const CHECKOUT_STARTED = 'checkout_started';
    public const SUBSCRIPTION_STARTED = 'subscription_started';

    /** Events the authenticated browser may report directly. */
    public const CLIENT_EVENTS = [
        self::FREE_SITUATION_OFFERED,
        self::FREE_SITUATION_OPENED,
        self::FREE_SITUATION_UPSELL_CLICKED,
    ];

    /** Funnel order used by the admin snapshot. */
    public const FUNNEL = [
        self::FREE_SITUATION_OFFERED,
        self::FREE_SITUATION_OPENED,
        self::FREE_SITUATION_STARTED,
        self::FREE_SITUATION_COMPLETED,
        self::FREE_SITUATION_UPSELL_CLICKED,
        self::CHECKOUT_STARTED,
        self::SUBSCRIPTION_STARTED,
    ];

    protected $fillable = ['user_id', 'event', 'metadata'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** Record the first time a learner reaches a milestone. Safe to retry. */
    public static function record(User $user, string $event, array $metadata = []): self
    {
        return static::firstOrCreate(
            ['user_id' => $user->id, 'event' => $event],
            ['metadata' => $metadata ?: null],
        );
    }
}
