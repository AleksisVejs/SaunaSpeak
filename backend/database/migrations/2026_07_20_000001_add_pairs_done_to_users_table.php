<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Cleared Kuulo vowel-contrast sets, per user: { set_id: iso_timestamp }.
// Same shape as transforms_done / scenarios_done / listening_done.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('pairs_done')->nullable()->after('transforms_done');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pairs_done');
        });
    }
};
