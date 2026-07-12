<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_progress', function (Blueprint $table) {
            // SM-2-style per-item ease: sentences the learner keeps lapsing on
            // get shorter intervals, easy ones stretch further. 2.5 = neutral.
            $table->float('ease')->default(2.5)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('user_progress', function (Blueprint $table) {
            $table->dropColumn('ease');
        });
    }
};
