<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Early evening local time: late enough that "you haven't practiced today"
// is meaningful, early enough to act on. Requires the scheduler cron
// (`php artisan schedule:run` every minute) - see DEPLOY.md.
Schedule::command('reminders:send')->dailyAt('17:00');
