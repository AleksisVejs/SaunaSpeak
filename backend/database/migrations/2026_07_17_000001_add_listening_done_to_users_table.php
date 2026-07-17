<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Listened-through Kuuntelu scenes, per user: { scene_id: iso_timestamp }.
// Same shape as scenarios_done - a small JSON map beats a pivot table here.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('listening_done')->nullable()->after('scenarios_done');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('listening_done');
        });
    }
};
