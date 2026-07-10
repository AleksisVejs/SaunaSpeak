<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewLog extends Model
{
    public const UPDATED_AT = null; // append-only log

    protected $fillable = ['user_id', 'kind', 'grade', 'created_at'];
}
