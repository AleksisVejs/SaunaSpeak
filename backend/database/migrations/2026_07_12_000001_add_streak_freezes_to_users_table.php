<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Streak freezes: earned every 7-day streak milestone (capped),
            // silently consumed when exactly one day is missed. Loss-aversion
            // churn insurance - one bad day shouldn't erase three weeks.
            $table->unsignedTinyInteger('streak_freezes')->default(0)->after('streak');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('streak_freezes');
        });
    }
};
