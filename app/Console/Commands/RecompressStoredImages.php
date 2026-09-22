<?php

namespace App\Console\Commands;

use App\Services\ImageCompressionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class RecompressStoredImages extends Command
{
    protected $signature = 'images:recompress
                            {--path= : Limit to a subdirectory under storage/app/public (e.g. placeholders/avatars)}
                            {--context= : Force a specific config/images.php context for every file}
                            {--force : Re-encode even files already under the ceiling}
                            {--dry-run : Report only, do not write}';

    protected $description = 'Recompress images already stored on the public disk';

    /**
     * Map a storage path to its config/images.php context.
     * Returned null means "use the default limits".
     *
     * The first matching prefix wins — keep the specific entries before the
     * generic ones.
     */
    private const CONTEXT_MAP = [
        'placeholders/avatars/'    => 'avatars',
        'placeholders/tenants/'    => 'tenant-logo',
        'placeholders/covers/'     => 'tenant-cover',
        'placeholders/gallery/'    => 'property',
        'placeholders/properties/' => 'property',
        'placeholders/events/'     => 'event',
        'placeholders/site/'       => 'site',
        'kyb-documents/'           => 'kyb-document',
        'avatars/'                 => 'avatars',
        'tenant-logos/'            => 'tenant-logo',
        'property-images/'         => 'property',
        'event-images/'            => 'event',
        'marker-icons/'            => 'marker-icon',
    ];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'];

    public function handle(ImageCompressionService $compressor): int
    {
        $root      = (string) ($this->option('path') ?: '');
        $forcedCtx = $this->option('context');
        $force     = (bool) $this->option('force');
        $dryRun    = (bool) $this->option('dry-run');

        $disk = Storage::disk('public');

        if (! $disk->exists($root)) {
            $this->error("Path not found on the public disk: {$root}");
            return self::FAILURE;
        }

        $this->info($dryRun
            ? 'Dry run — no files will be modified.'
            : 'Recompressing stored images…');

        $this->newLine();

        $files = $disk->allFiles($root);

        $counts = [
            'scanned'    => 0,
            'compressed' => 0,
            'skipped'    => 0,
            'failed'     => 0,
        ];

        $bytesBefore = 0;
        $bytesAfter  = 0;

        foreach ($files as $relativePath) {
            $ext = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

            if (! in_array($ext, self::IMAGE_EXTENSIONS, true)) {
                continue;
            }

            $counts['scanned']++;

            $absolute = $disk->path($relativePath);
            $context  = $forcedCtx ?: $this->resolveContext($relativePath);

            try {
                $before = @filesize($absolute) ?: 0;

                if ($dryRun) {
                    // In dry-run, we still call compressInPlace but on a
                    // temporary copy so we can measure the outcome without
                    // touching the original.
                    $temp = tempnam(sys_get_temp_dir(), 'recomp_') . '.' . $ext;
                    copy($absolute, $temp);

                    try {
                        $did = $compressor->compressInPlace($temp, $context, force: $force);
                        $after = @filesize($temp) ?: 0;
                    } finally {
                        @unlink($temp);
                    }
                } else {
                    $did   = $compressor->compressInPlace($absolute, $context, force: $force);
                    clearstatcache(true, $absolute);
                    $after = @filesize($absolute) ?: 0;
                }

                $bytesBefore += $before;
                $bytesAfter  += $after;

                if ($did) {
                    $counts['compressed']++;
                    $saved = $before - $after;
                    $pct   = $before > 0 ? round(($saved / $before) * 100, 1) : 0;

                    $this->line(sprintf(
                        '  <fg=green;options=bold>✓</> %s  %s → %s  <fg=gray>(−%s%%)</>',
                        $this->shorten($relativePath),
                        $this->humanBytes($before),
                        $this->humanBytes($after),
                        $pct,
                    ));
                } else {
                    $counts['skipped']++;
                }
            } catch (Throwable $e) {
                $counts['failed']++;
                $this->line(sprintf(
                    '  <fg=red>✗</> %s  <fg=gray>(%s)</>',
                    $this->shorten($relativePath),
                    $e->getMessage(),
                ));
            }
        }

        // ── Summary ───────────────────────────────────────────
        $this->newLine();
        $this->table(
            ['Scanned', 'Compressed', 'Skipped', 'Failed'],
            [[
                $counts['scanned'],
                $counts['compressed'],
                $counts['skipped'],
                $counts['failed'],
            ]]
        );

        if ($bytesBefore > 0) {
            $totalSaved = $bytesBefore - $bytesAfter;
            $pct        = round(($totalSaved / $bytesBefore) * 100, 1);

            $this->line(sprintf(
                '  <fg=cyan>Total bytes:</> %s → %s  <fg=green>(−%s, −%s%%)</>',
                $this->humanBytes($bytesBefore),
                $this->humanBytes($bytesAfter),
                $this->humanBytes($totalSaved),
                $pct,
            ));
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run — no files were modified.');
        }

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveContext(string $relativePath): ?string
    {
        foreach (self::CONTEXT_MAP as $prefix => $context) {
            if (Str::startsWith($relativePath, $prefix)) {
                return $context;
            }
        }
        return null;
    }

    private function shorten(string $path, int $max = 70): string
    {
        if (strlen($path) <= $max) {
            return $path;
        }
        return '…' . substr($path, -($max - 1));
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1024 / 1024, 2) . ' MB';
    }
}