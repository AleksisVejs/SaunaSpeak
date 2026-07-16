<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One in-app feedback submission; listed newest-first in the admin panel. */
class Feedback extends Model
{
    public const UPDATED_AT = null; // append-only, like ReviewLog

    protected $table = 'feedback';

    protected $fillable = ['user_id', 'message', 'created_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
