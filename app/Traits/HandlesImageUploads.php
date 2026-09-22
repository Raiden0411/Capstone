<?php

namespace App\Traits;

use App\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

trait HandlesImageUploads
{
    /**
     * Store an uploaded file on disk and compress it in place via
     * ImageCompressionService — for image mimes only.
     *
     * Non-image mimes (PDFs, DOCX, ZIP, ...) are stored as-is. Skipping
     * the compression call for them is deliberate: ImageCompressionService
     * is a size policer, not a mime validator — its decoder throws on any
     * format it can't read, and the service logs every decode failure as
     * a warning. Uploading a multi-megabyte PDF through a shared upload
     * path (e.g. the KYB flow) would otherwise spam laravel.log with
     * "Image compression failed" entries for a file the compressor was
     * never meant to touch.
     *
     * @param  UploadedFile  $file     The uploaded file.
     * @param  string        $folder   Storage subdirectory (e.g. 'tenant-logos').
     * @param  string        $disk     Filesystem disk name.
     * @param  string|null   $context  Key into config/images.php 'contexts'.
     * @return string|null             Stored relative path, or null on failure.
     */
    protected function storeImage(
        UploadedFile $file,
        string $folder,
        string $disk = 'public',
        ?string $context = null
    ): ?string {
        try {
            $relativePath = $file->store($folder, $disk);

            if (! $relativePath) {
                return null;
            }

            $mime = (string) $file->getMimeType();

            if ($mime !== '' && str_starts_with($mime, 'image/')) {
                $absolutePath = Storage::disk($disk)->path($relativePath);
                app(ImageCompressionService::class)->compressInPlace($absolutePath, $context);
            }

            return $relativePath;
        } catch (Throwable $e) {
            Log::warning('storeImage failed', [
                'folder'  => $folder,
                'disk'    => $disk,
                'context' => $context,
                'error'   => $e->getMessage(),
            ]);

            return null;
        }
    }
}