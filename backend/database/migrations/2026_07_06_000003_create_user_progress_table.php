<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sentence_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['new', 'learning', 'review', 'mastered'])->default('new');
            $table->timestamp('next_review_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'sentence_id']);
            $table->index(['user_id', 'next_review_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_progress');
    }
};
