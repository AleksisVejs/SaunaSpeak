<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The Stripe subscription status ('trialing', 'active', 'past_due', ...)
        // as of the last webhook. premium_until alone cannot tell a paying
        // subscriber from one on the free trial - both just have a future date.
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_status')->nullable()->after('stripe_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('stripe_status');
        });
    }
};
