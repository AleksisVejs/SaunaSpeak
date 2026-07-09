<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Passed level checkpoints, e.g. {"A0": "2026-07-09T18:00:00Z"}.
        Schema::table('users', function (Blueprint $table) {
            $table->json('checkpoints')->nullable()->after('last_active_date');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('checkpoints');
        });
    }
};
