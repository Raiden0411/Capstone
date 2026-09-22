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

// Nightly stale-row pruning.
// Deletes expired cache entries, stale cache locks, dead sessions, and
// failed jobs older than 14 days. Runs at 03:15 local — off-peak,
// separate from the event deactivation job.
//
// The command is idempotent: running it twice does nothing extra.
// Session cutoff auto-scales to at least 2× SESSION_LIFETIME (floor
// 30 days) so a future change to that config can't turn the prune into
// a live-session killer.
Schedule::command('cache:prune-stale')
    ->dailyAt('03:15')
    ->withoutOverlapping()
    ->runInBackground();

// Slow-query log harvest.
// Aggregates the JSONL lines in storage/logs/slow-queries.log into the
// slow_query_aggregates table consumed by /platform/health/queries.
//
// Runs every 5 minutes. `withoutOverlapping(10)` gives a 10-minute
// lock window — if a harvest somehow takes longer than that, a second
// instance will bail out rather than stack. Rotates the log atomically
// (rename + touch), so slow queries arriving mid-harvest are never lost.
Schedule::command('slow-queries:harvest')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->runInBackground();