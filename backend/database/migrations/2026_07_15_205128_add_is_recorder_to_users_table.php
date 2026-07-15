<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Recording rights: lets a trusted native speaker use the in-app studio
// (/record) to replace TTS audio with their own voice. Granted via
// `php artisan user:recorder <email>` - never through the web app.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_recorder')->default(false)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_recorder');
        });
    }
};
