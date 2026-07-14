<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A broken streak is remembered here for a few days so the learner can
     * spend XP to relight it (POST /streak/repair) instead of quitting.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('broken_streak')->default(0);
            $table->date('streak_broken_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['broken_streak', 'streak_broken_date']);
        });
    }
};
