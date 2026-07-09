<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sentences', function (Blueprint $table) {
            // Dialogue support: who says the line, and the line it replies to.
            $table->string('speaker', 8)->nullable()->after('written_text');
            $table->string('context_text')->nullable()->after('speaker');
        });
    }

    public function down(): void
    {
        Schema::table('sentences', function (Blueprint $table) {
            $table->dropColumn(['speaker', 'context_text']);
        });
    }
};
