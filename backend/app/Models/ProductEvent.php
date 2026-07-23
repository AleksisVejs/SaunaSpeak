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

    // Signup → first graded card. A quarter of accounts earn no XP at all and
    // leave no other trace, so without these four the whole stretch between
    // registering and the first review is invisible. SESSION_SERVED is written
    // server-side on purpose: it still lands when the browser blocks or breaks,
    // so "we sent cards, nothing rendered" is distinguishable from "never asked".
    public const ONBOARDING_STARTED = 'onboarding_started';
    public const ONBOARDING_FINISHED = 'onboarding_finished';
    public const SESSION_SERVED = 'session_served';
    public const FIRST_CARD_RENDERED = 'first_card_rendered';

    /** Events the authenticated browser may report directly. */
    public const CLIENT_EVENTS = [
        self::FREE_SITUATION_OFFERED,
        self::FREE_SITUATION_OPENED,
        self::FREE_SITUATION_UPSELL_CLICKED,
        self::ONBOARDING_STARTED,
        self::ONBOARDING_FINISHED,
        self::FIRST_CARD_RENDERED,
    ];

    /**
     * Funnel order used by the admin snapshot.
     *
     * Consumed POSITIONALLY by AdminController (the first five are the free
     * Situation steps, the rest are downstream revenue). Appending anything
     * here silently corrupts that split - new funnels get their own const.
     */
    public const FUNNEL = [
        self::FREE_SITUATION_OFFERED,
        self::FREE_SITUATION_OPENED,
        self::FREE_SITUATION_STARTED,
        self::FREE_SITUATION_COMPLETED,
        self::FREE_SITUATION_UPSELL_CLICKED,
        self::CHECKOUT_STARTED,
        self::SUBSCRIPTION_STARTED,
    ];

    /** Signup → first card, reported separately from the Situation funnel. */
    public const ACTIVATION_FUNNEL = [
        self::ONBOARDING_STARTED,
        self::ONBOARDING_FINISHED,
        self::SESSION_SERVED,
        self::FIRST_CARD_RENDERED,
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
