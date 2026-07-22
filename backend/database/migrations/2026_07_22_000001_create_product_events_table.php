<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Funnel milestones, not noisy clickstream. One row per learner
            // and milestone makes retries idempotent and analysis simple.
            $table->unique(['user_id', 'event']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_events');
    }
};
