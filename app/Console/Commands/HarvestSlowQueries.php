<?php

namespace App\Console\Commands;

use App\Models\SlowQueryAggregate;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HarvestSlowQueries extends Command
{
    protected $signature = 'slow-queries:harvest
                            {--dry-run : Parse and report without writing to the database or touching the log}
                            {--keep-archive : After a real run, keep the rotated log file instead of deleting it}';

    protected $description = 'Aggregate slow-query log entries into the slow_query_aggregates table';

    /**
     * Maximum length of the sql_sample stored in the DB.
     */
    private const MAX_SQL_SAMPLE_LENGTH = 2000;

    /**
     * Maximum length of the bindings_sample JSON.
     */
    private const MAX_BINDINGS_SAMPLE_LENGTH = 2000;

    public function handle(): int
    {
        $logPath = storage_path('logs/slow-queries.log');

        if (! is_file($logPath) || filesize($logPath) === 0) {
            $this->info('No slow-query log entries to harvest.');
            return self::SUCCESS;
        }

        // ── Dry run: read in place, DO NOT rotate or delete ──
        if ($this->option('dry-run')) {
            [$buckets, $lineCount, $parseErrors] = $this->parseFile($logPath);

            if (empty($buckets)) {
                $this->info("Parsed {$lineCount} line(s); no valid entries ({$parseErrors} skipped). Log untouched.");
                return self::SUCCESS;
            }

            $this->printBuckets($buckets);
            $this->info(
                'Dry run — ' . count($buckets) . ' unique query shape(s) parsed, '
                . 'nothing written, log untouched.'
            );

            return self::SUCCESS;
        }

        // ── Real run: atomic rotate ──────────────────────
        // rename() is atomic on the same filesystem. During the tiny
        // window between rename and touch, any append lands either in
        // the archive (captured) or the fresh empty file (next harvest).
        $archivePath = storage_path(
            'logs/slow-queries-archive-' . now()->format('Y-m-d-His') . '.log'
        );

        if (! @rename($logPath, $archivePath)) {
            $this->error("Could not rotate the log at {$logPath}.");
            return self::FAILURE;
        }

        @touch($logPath);
        @chmod($logPath, 0644);

        // ── Parse the archive ────────────────────────────
        [$buckets, $lineCount, $parseErrors] = $this->parseFile($archivePath);

        if (empty($buckets)) {
            $this->info("Parsed {$lineCount} line(s); no valid entries ({$parseErrors} skipped).");

            if (! $this->option('keep-archive')) {
                @unlink($archivePath);
            }

            return self::SUCCESS;
        }

        // ── Upsert ───────────────────────────────────────
        $written = 0;
        $failed  = 0;

        foreach ($buckets as $bucket) {
            try {
                $existing = SlowQueryAggregate::where('query_hash', $bucket['query_hash'])->first();

                if ($existing) {
                    $newCount = $existing->count + $bucket['count'];
                    $newTotal = (float) $existing->total_ms + $bucket['total_ms'];

                    $existing->update([
                        'count'        => $newCount,
                        'total_ms'     => round($newTotal, 2),
                        'avg_ms'       => $newCount > 0 ? round($newTotal / $newCount, 2) : 0,
                        'max_ms'       => round(max((float) $existing->max_ms, $bucket['max_ms']), 2),
                        'last_seen_at' => $bucket['last_seen_at'],
                        // Preserve the original route_name — the first
                        // route to hit this query shape is usually the
                        // interesting one.
                        'route_name'   => $existing->route_name ?: $bucket['route_name'],
                    ]);
                } else {
                    SlowQueryAggregate::create([
                        'query_hash'      => $bucket['query_hash'],
                        'sql_sample'      => $bucket['sql_sample'],
                        'bindings_sample' => $bucket['bindings_sample'],
                        'route_name'      => $bucket['route_name'],
                        'count'           => $bucket['count'],
                        'total_ms'        => round($bucket['total_ms'], 2),
                        'avg_ms'          => round($bucket['total_ms'] / max(1, $bucket['count']), 2),
                        'max_ms'          => round($bucket['max_ms'], 2),
                        'first_seen_at'   => $bucket['first_seen_at'],
                        'last_seen_at'    => $bucket['last_seen_at'],
                    ]);
                }

                $written++;
            } catch (Throwable $e) {
                $failed++;
                Log::warning('Slow-query harvest: row failed', [
                    'hash'  => $bucket['query_hash'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! $this->option('keep-archive')) {
            @unlink($archivePath);
        }

        $this->info(
            "Harvested {$lineCount} line(s) → {$written} unique quer" . ($written === 1 ? 'y' : 'ies')
            . ($failed > 0 ? ", {$failed} failed" : '')
            . ($parseErrors > 0 ? ", {$parseErrors} unparseable line(s) skipped" : '')
            . '.'
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────
    //  Shared helpers
    // ─────────────────────────────────────────────────────

    /**
     * Parse a slow-query log file into aggregated buckets keyed by
     * sha256(sql). Does NOT move, delete, or modify the file.
     *
     * @return array{0: array<string, array<string, mixed>>, 1: int, 2: int}
     *         [buckets, lineCount, parseErrors]
     */
    private function parseFile(string $path): array
    {
        $handle = @fopen($path, 'r');
        if (! $handle) {
            return [[], 0, 0];
        }

        $buckets     = [];
        $lineCount   = 0;
        $parseErrors = 0;

        while (($line = fgets($handle)) !== false) {
            $lineCount++;
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $payload = json_decode($line, true);
            if (! is_array($payload)) {
                $parseErrors++;
                continue;
            }

            // Monolog's JsonFormatter emits
            // { message, context, level, level_name, channel, datetime, extra }.
            // Our DB::listen handler places
            // { sql, bindings, time_ms, connection, route } inside context.
            $ctx = $payload['context'] ?? null;
            if (! is_array($ctx)) {
                $parseErrors++;
                continue;
            }

            $sql = (string) ($ctx['sql'] ?? '');
            if ($sql === '') {
                $parseErrors++;
                continue;
            }

            $hash = hash('sha256', $sql);

            if (! isset($buckets[$hash])) {
                $buckets[$hash] = [
                    'query_hash'      => $hash,
                    'sql_sample'      => Str::limit($sql, self::MAX_SQL_SAMPLE_LENGTH, '…'),
                    'bindings_sample' => $this->encodeBindings($ctx['bindings'] ?? null),
                    'route_name'      => $this->truncateRoute($ctx['route'] ?? null),
                    'count'           => 0,
                    'total_ms'        => 0.0,
                    'max_ms'          => 0.0,
                    'first_seen_at'   => $this->parseDatetime($payload['datetime'] ?? null),
                    'last_seen_at'    => $this->parseDatetime($payload['datetime'] ?? null),
                ];
            }

            $ms = (float) ($ctx['time_ms'] ?? 0);

            $buckets[$hash]['count']++;
            $buckets[$hash]['total_ms'] += $ms;

            if ($ms > $buckets[$hash]['max_ms']) {
                $buckets[$hash]['max_ms'] = $ms;
            }

            $buckets[$hash]['last_seen_at'] = $this->parseDatetime($payload['datetime'] ?? null);
        }

        fclose($handle);

        return [$buckets, $lineCount, $parseErrors];
    }

    /**
     * @param array<string, array<string, mixed>> $buckets
     */
    private function printBuckets(array $buckets): void
    {
        $this->table(
            ['Hash (8)', 'Count', 'Avg ms', 'Max ms', 'SQL (first 60)'],
            collect($buckets)
                ->sortByDesc(fn ($b) => $b['total_ms'])
                ->map(fn ($b) => [
                    substr($b['query_hash'], 0, 8),
                    $b['count'],
                    round($b['total_ms'] / max(1, $b['count']), 2),
                    round($b['max_ms'], 2),
                    Str::limit($b['sql_sample'], 60),
                ])
                ->values()
                ->all()
        );
    }

    private function encodeBindings(mixed $bindings): ?string
    {
        if (! is_array($bindings) || empty($bindings)) {
            return null;
        }

        try {
            $json = json_encode($bindings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (! is_string($json)) {
                return null;
            }

            return Str::limit($json, self::MAX_BINDINGS_SAMPLE_LENGTH, '…');
        } catch (Throwable) {
            return null;
        }
    }

    private function truncateRoute(mixed $route): ?string
    {
        if (! is_string($route) || $route === '') {
            return null;
        }

        return Str::limit($route, 190, '');
    }

    private function parseDatetime(mixed $raw): CarbonInterface
    {
        if (is_string($raw) && $raw !== '') {
            try {
                return Carbon::parse($raw);
            } catch (Throwable) {
                // fall through
            }
        }

        return now();
    }
}