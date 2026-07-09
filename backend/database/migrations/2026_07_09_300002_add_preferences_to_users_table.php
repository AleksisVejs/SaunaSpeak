<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Learner preferences from the intake quiz (goal, minutes/day, dailyGoal),
        // mirrored from localStorage so they survive device switches.
        Schema::table('users', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('checkpoints');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }
};
