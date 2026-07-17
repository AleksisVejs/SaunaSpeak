<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cleared Taivutus sets, per user: { set_id: iso_timestamp }. Same shape as
// scenarios_done / listening_done.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('transforms_done')->nullable()->after('listening_done');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('transforms_done');
        });
    }
};
