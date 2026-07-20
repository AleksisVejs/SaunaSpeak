<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Hourly: the command itself mails each learner only in the hour matching
// the practice time they picked at intake, in their own timezone (legacy
// accounts default to 17:00 local). Requires the scheduler cron
// (`php artisan schedule:run` every minute) - see DEPLOY.md.
Schedule::command('reminders:send')->hourly();
