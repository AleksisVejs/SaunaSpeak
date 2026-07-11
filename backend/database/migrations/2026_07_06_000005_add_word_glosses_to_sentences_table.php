<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sentences', function (Blueprint $table) {
            // Per-word dictionary: { "oon": "am - spoken form of 'olen'", ... }
            // Keys are lowercase words stripped of punctuation.
            $table->json('word_glosses')->nullable()->after('english_text');
        });
    }

    public function down(): void
    {
        Schema::table('sentences', function (Blueprint $table) {
            $table->dropColumn('word_glosses');
        });
    }
};
