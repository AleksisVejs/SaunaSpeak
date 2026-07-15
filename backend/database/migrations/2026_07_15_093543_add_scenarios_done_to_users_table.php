<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Completed Situations, per user: { scenario_id: iso_timestamp }. Mirrors
// the checkpoints column - a small JSON map beats a pivot table at this size.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('scenarios_done')->nullable()->after('checkpoints');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('scenarios_done');
        });
    }
};
