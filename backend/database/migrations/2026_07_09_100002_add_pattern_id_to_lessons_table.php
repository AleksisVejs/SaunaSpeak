<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each lesson can surface one key grammar pattern as a "Why this works" note.
        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('pattern_id')->nullable()->after('order_index')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pattern_id');
        });
    }
};
