<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Error-driven SRS: every correction Väinö (or a scenario character)
        // hands out in chat becomes a review card here - the learner's own
        // mistakes, resurfaced as retrieval practice.
        Schema::create('user_mistakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('attempt', 500);   // what the learner actually wrote
            $table->string('corrected', 500); // the character's gentle fix
            $table->string('hash', 40);       // normalized corrected - dedup key
            $table->string('source', 32)->nullable(); // scenario id; null = Väinö
            $table->string('status', 12)->default('new');
            $table->timestamp('next_review_at')->nullable();
            $table->unsignedInteger('reviews')->default(0);
            $table->timestamps();
            // Same target sentence = same card; repeating the mistake reopens
            // it instead of duplicating it.
            $table->unique(['user_id', 'hash']);
        });

        // One row per user per day of chatting - the raw data behind the
        // "chat messages this week" insight. A counter, not a transcript:
        // conversation content is never stored.
        Schema::create('chat_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('messages')->default(0);
            $table->unique(['user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_days');
        Schema::dropIfExists('user_mistakes');
    }
};
