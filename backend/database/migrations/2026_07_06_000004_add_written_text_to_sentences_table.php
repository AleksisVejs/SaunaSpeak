<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sentences', function (Blueprint $table) {
            // Kirjakieli (standard written Finnish) reference form.
            // Null when the sentence is identical in both registers.
            $table->string('written_text')->nullable()->after('finnish_text');
        });
    }

    public function down(): void
    {
        Schema::table('sentences', function (Blueprint $table) {
            $table->dropColumn('written_text');
        });
    }
};
