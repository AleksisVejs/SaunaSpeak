<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // IANA timezone from the browser, so streaks and "today" follow the
            // learner's clock instead of the server's (null = app timezone).
            $table->string('timezone', 64)->nullable()->after('preferences');
            // Review-reminder emails are opt-out; the sender skips false.
            $table->boolean('review_emails')->default(true)->after('timezone');
        });

        Schema::table('user_progress', function (Blueprint $table) {
            // Last scheduled gap in days. Lets mastered items keep compounding
            // (interval x ease) instead of being pinned to a 30-day ceiling
            // forever - without it the review load grows linearly with
            // everything ever learned. Null = legacy row, treated as stage default.
            $table->unsignedSmallInteger('interval_days')->nullable()->after('ease');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'review_emails']);
        });

        Schema::table('user_progress', function (Blueprint $table) {
            $table->dropColumn('interval_days');
        });
    }
};
