<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Turn the word bank into an Anki-style flashcard deck: each tapped word
        // carries its own spaced-repetition schedule.
        Schema::table('user_words', function (Blueprint $table) {
            $table->string('status', 16)->default('new')->after('gloss');
            $table->timestamp('next_review_at')->nullable()->after('status');
            $table->unsignedInteger('reviews')->default(0)->after('next_review_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_words', function (Blueprint $table) {
            $table->dropColumn(['status', 'next_review_at', 'reviews']);
        });
    }
};
