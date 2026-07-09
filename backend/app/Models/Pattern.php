<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pattern extends Model
{
    protected $fillable = ['title', 'summary', 'examples', 'order_index'];

    protected $casts = ['examples' => 'array'];

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
