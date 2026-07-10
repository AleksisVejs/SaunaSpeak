<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Shared hosts often cap index keys (MyISAM: 1000 bytes; older InnoDB:
        // 767). 191 * 4 bytes (utf8mb4) fits everywhere.
        Schema::defaultStringLength(191);
    }
}
