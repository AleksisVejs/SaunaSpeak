<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Löyly+ subscription state, driven by Stripe webhooks.
        Schema::table('users', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('preferences');
            $table->string('stripe_subscription_id')->nullable()->after('stripe_customer_id');
            $table->timestamp('premium_until')->nullable()->after('stripe_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'stripe_subscription_id', 'premium_until']);
        });
    }
};
