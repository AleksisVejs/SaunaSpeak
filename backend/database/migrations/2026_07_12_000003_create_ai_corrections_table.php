<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Learners make identical mistakes on the same sentences; caching
        // (expected, attempt) → correction makes repeats instant and free.
        Schema::create('ai_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('hash', 40)->unique(); // sha1 of normalized expected|attempt
            $table->string('expected_sentence', 500);
            $table->string('user_sentence', 500);
            $table->string('corrected', 500);
            $table->string('explanation', 1000);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_corrections');
    }
};
