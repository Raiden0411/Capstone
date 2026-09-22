<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Compresses uploaded images in place, targeting a per-context size ceiling.
 *
 * Strategy:
 *   1. Skip if the file is already under the target size AND dimensions.
 *      — Bypassed when $force = true.
 *   2. Skip anything Intervention can't decode (PDF, SVG, etc.).
 *   3. Auto-orientation + EXIF stripping are handled by the ImageManager
 *      constructor (v3 API). There is no `orientate()` or `strip()` method
 *      on the v3 ImageInterface — attempting to call either fatals.
 *   4. Scale down if wider/taller than the context's max dimensions.
 *   5. Walk the quality ladder until the encoded output fits.
 *   6. If the quality floor still overflows, scale down 10% and retry.
 *   7. Give up after a hard iteration cap and log a warning — the original
 *      file is left in place, so a failed compression never breaks upload.
 *
 * Never throws to the caller. Failure = "the file stays as uploaded".
 */
class ImageCompressionService
{
    private ImageManager $manager;

    /** Hard cap on resize-and-retry iterations to prevent runaway loops. */
    private const MAX_RESIZE_ATTEMPTS = 5;

    public function __construct()
    {
        $driver = config('images.driver', 'gd');

        // Auto-fallback: if imagick was requested but isn't loaded, use GD
        // rather than crashing on the first upload.
        if ($driver === 'imagick' && ! extension_loaded('imagick')) {
            Log::warning('IMAGE_DRIVER=imagick requested but extension not loaded; falling back to GD.');
            $driver = 'gd';
        }

        // ── v3 constructor options ──────────────────────────────
        // autoOrientation (bool, default true)
        //   Rotate the decoded image to match its EXIF orientation tag.
        //   Without this, iPhone portrait photos end up sideways because
        //   the rotation lives in EXIF metadata that the encoder drops.
        //
        // strip (bool, default false)
        //   Remove EXIF / IPTC / comment metadata during encode. Privacy
        //   (GPS, device model) + size. GD discards EXIF on its own; this
        //   is what makes Imagick behave the same way.
        $autoOrientation = (bool) config('images.auto_orientate', true);
        $stripExif       = (bool) config('images.strip_exif', true);

        $this->manager = new ImageManager(
            driver: $driver === 'imagick' ? new ImagickDriver() : new GdDriver(),
            autoOrientation: $autoOrientation,
            strip: $stripExif,
        );
    }

    /**
     * Compress an image file in place.
     *
     * @param  string      $absolutePath  Full filesystem path to the file.
     * @param  string|null $context       Config context key from config/images.php.
     * @param  bool        $force         When true, bypass the under-limit skip
     *                                    and always re-encode. The original file
     *                                    is kept if the re-encode grows it.
     * @return bool        True if the file was re-encoded, false otherwise.
     */
    public function compressInPlace(
        string $absolutePath,
        ?string $context = null,
        bool $force = false,
    ): bool {
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return false;
        }

        $limits = $this->resolveLimits($context);

        try {
            $originalBytes = filesize($absolutePath) ?: 0;

            // Already under the ceiling AND under the dimension caps?
            // Skip — unless the caller asked for a forced re-encode.
            if (! $force
                && $originalBytes > 0
                && $originalBytes <= $limits['max_bytes']
                && ! $this->exceedsDimensions($absolutePath, $limits)) {
                return false;
            }

            // Orientation + strip are applied by the manager at decode/encode
            // time — no method calls needed here.
            $image = $this->manager->read($absolutePath);

            // ── Scale down to dimension caps ────────────────
            if ($limits['max_width'] > 0 || $limits['max_height'] > 0) {
                $image->scaleDown(
                    width:  $limits['max_width']  ?: null,
                    height: $limits['max_height'] ?: null,
                );
            }

            // ── Pick output format ──────────────────────────
            $format = $this->resolveOutputFormat($absolutePath, $image);

            // ── Walk the ladder + resize loop ───────────────
            $ladder  = config('images.quality_ladder', [88, 80, 72, 64, 56]);
            $attempt = 0;

            while (true) {
                foreach ($ladder as $quality) {
                    $encodedBytes = $this->encodeAndMeasure($image, $format, $quality);

                    if ($encodedBytes !== null && $encodedBytes <= $limits['max_bytes']) {
                        // Safety: in force mode, if the re-encode produced a
                        // LARGER file than the original AND the original was
                        // already under the ceiling, keep the original. Some
                        // already-optimized JPEGs grow when re-encoded.
                        if ($force
                            && $originalBytes > 0
                            && $encodedBytes >= $originalBytes
                            && $originalBytes <= $limits['max_bytes']) {
                            return false;
                        }

                        $this->writeOut($image, $absolutePath, $format, $quality);
                        return true;
                    }
                }

                // Quality floor still too large → scale down 10% and retry.
                $attempt++;
                if ($attempt >= self::MAX_RESIZE_ATTEMPTS) {
                    Log::warning('Image compression exhausted resize attempts', [
                        'path'       => $absolutePath,
                        'context'    => $context,
                        'size_bytes' => $originalBytes,
                        'target_kb'  => $limits['max_size_kb'],
                    ]);
                    return false;
                }

                $image->scaleDown(width: (int) ($image->width() * 0.9));
            }
        } catch (Throwable $e) {
            Log::warning('Image compression failed — file left as uploaded', [
                'path'    => $absolutePath,
                'context' => $context,
                'error'   => $e->getMessage(),
                'type'    => get_class($e),
            ]);
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════
    // Internal helpers
    // ═══════════════════════════════════════════════════════

    /** @return array{max_size_kb:int,max_bytes:int,max_width:int,max_height:int} */
    private function resolveLimits(?string $context): array
    {
        $defaults = [
            'max_size_kb' => (int) config('images.max_size_kb', 2048),
            'max_width'   => (int) config('images.max_width', 2560),
            'max_height'  => (int) config('images.max_height', 2560),
        ];

        if ($context) {
            $override = config("images.contexts.{$context}", []);
            $defaults = array_merge($defaults, $override);
        }

        $defaults['max_bytes'] = $defaults['max_size_kb'] * 1024;

        return $defaults;
    }

    private function exceedsDimensions(string $path, array $limits): bool
    {
        try {
            $image = $this->manager->read($path);

            return ($limits['max_width'] > 0 && $image->width() > $limits['max_width'])
                || ($limits['max_height'] > 0 && $image->height() > $limits['max_height']);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Decide the output format based on the input and config.
     *
     * @return 'jpeg'|'png'|'webp'
     */
    private function resolveOutputFormat(string $path, $image): string
    {
        $ext   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isPng = $ext === 'png';

        // PNG with real transparency: keep as PNG (JPEG has no alpha).
        // PNG without transparency: convert to JPEG (or WEBP) to shave 5–10×.
        if ($isPng) {
            $hasAlpha = method_exists($image, 'isOpaque')
                ? ! $image->isOpaque()
                : false;

            if (! $hasAlpha && config('images.convert_png_to_jpeg', true)) {
                return config('images.prefer_modern_format', false) ? 'webp' : 'jpeg';
            }

            return 'png';
        }

        if ($ext === 'webp') {
            return 'webp';
        }

        return config('images.prefer_modern_format', false) ? 'webp' : 'jpeg';
    }

    /**
     * Encode to an in-memory string and report its byte size.
     * Returns null on encoder failure.
     *
     * The `$format` parameter is documented as a literal union so
     * PHPStan can prove the `match` below is exhaustive. Every caller
     * passes the value returned by resolveOutputFormat(), which is
     * itself declared to return exactly this union.
     *
     * @param  'jpeg'|'png'|'webp'  $format
     */
    private function encodeAndMeasure($image, string $format, int $quality): ?int
    {
        try {
            $encoded = match ($format) {
                'jpeg' => $image->toJpeg(quality: $quality),
                'webp' => $image->toWebp(quality: $quality),
                'png'  => $image->toPng(),
            };

            return strlen((string) $encoded);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The `$format` parameter is documented as a literal union for the
     * same reason as encodeAndMeasure(): the `match` below must be
     * provably exhaustive to PHPStan.
     *
     * @param  'jpeg'|'png'|'webp'  $format
     */
    private function writeOut($image, string $absolutePath, string $format, int $quality): void
    {
        match ($format) {
            'jpeg' => $image->toJpeg(quality: $quality)->save($absolutePath),
            'webp' => $image->toWebp(quality: $quality)->save($absolutePath),
            'png'  => $image->toPng()->save($absolutePath),
        };
    }
}