<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Commands
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| The `schedule:run` command must be invoked every minute from the
| system's cron (production) or `php artisan schedule:work` (local).
|
|   * * * * * cd /path/to/Capstone && php artisan schedule:run >> /dev/null 2>&1
|
*/

// Auto-deactivate events whose end_date has passed.
// Runs hourly at minute :05, with a 1-hour grace period so events that
// end mid-day aren't flipped off at the exact second they finish.
Schedule::command('events:deactivate-ended --grace=1')
    ->hourly()
    ->at('5')
    ->withoutOverlapping()
    ->runInBackground();