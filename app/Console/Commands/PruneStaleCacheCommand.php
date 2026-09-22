<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneStaleCacheCommand extends Command
{
    protected $signature = 'cache:prune-stale
                            {--dry-run : Report what would be deleted without writing}';

    protected $description = 'Delete expired cache, cache_locks, sessions, and failed_jobs rows';

    public function handle(): int
    {
        $now = now()->timestamp;

        // 1-day grace after nominal expiry. Cache reads are atomic
        // (get + delete), but the grace costs nothing and avoids any
        // theoretical race with a concurrent cache miss repopulating.
        $cacheCutoff = $now - 86400;

        // Sessions: at least 30 days, or 2× the configured session
        // lifetime — whichever is GREATER. This way, if you ever raise
        // SESSION_LIFETIME to a year, the prune stays far behind it and
        // cannot delete a session a user is still relying on.
        $sessionLifetimeMinutes = (int) config('session.lifetime', 120);
        $sessionCutoffDays      = max(
            30,
            (int) ceil($sessionLifetimeMinutes / (60 * 24)) * 2
        );
        $sessionCutoff = $now - ($sessionCutoffDays * 86400);

        // Failed jobs older than 14 days. Adjust if you rely on
        // long-term failure forensics.
        $failedJobsCutoff = now()->subDays(14);

        $counts = [
            'cache'       => DB::table('cache')
                ->where('expiration', '<', $cacheCutoff)
                ->count(),

            'cache_locks' => DB::table('cache_locks')
                ->where('expiration', '<', $cacheCutoff)
                ->count(),

            'sessions'    => DB::table('sessions')
                ->where('last_activity', '<', $sessionCutoff)
                ->count(),

            'failed_jobs' => DB::table('failed_jobs')
                ->where('failed_at', '<', $failedJobsCutoff)
                ->count(),
        ];

        if ($this->option('dry-run')) {
            $this->table(
                ['Table', 'Would delete', 'Cutoff (days ago)'],
                [
                    ['cache',       $counts['cache'],       '1'],
                    ['cache_locks', $counts['cache_locks'], '1'],
                    ['sessions',    $counts['sessions'],    (string) $sessionCutoffDays],
                    ['failed_jobs', $counts['failed_jobs'], '14'],
                ]
            );

            $this->info('Dry run — nothing was deleted.');

            return self::SUCCESS;
        }

        // Execute. Order: largest table first — cache is almost always
        // the biggest by row count, and freeing its rows first keeps the
        // subsequent deletes fast (less index churn).
        DB::table('cache')
            ->where('expiration', '<', $cacheCutoff)
            ->delete();

        DB::table('cache_locks')
            ->where('expiration', '<', $cacheCutoff)
            ->delete();

        DB::table('sessions')
            ->where('last_activity', '<', $sessionCutoff)
            ->delete();

        DB::table('failed_jobs')
            ->where('failed_at', '<', $failedJobsCutoff)
            ->delete();

        $total = array_sum($counts);

        $this->info(
            "Pruned {$total} stale row(s) — " . json_encode($counts)
        );

        if ($total > 0) {
            Log::info('cache:prune-stale completed', $counts);
        }

        return self::SUCCESS;
    }
}