<?php

namespace Tests\Feature;

use App\Services\ImageCompressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Image compression never-throws contract.
 *
 * The service is called on every image upload. It MUST never throw to
 * its caller — a failed compression means "the file stays as uploaded",
 * not "the upload failed". These tests lock that contract in.
 */
class ImageCompressionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_file_returns_false_without_throwing(): void
    {
        $service = app(ImageCompressionService::class);

        $result = $service->compressInPlace(
            '/tmp/this-file-does-not-exist-' . uniqid()
        );

        $this->assertFalse($result);
    }

    public function test_non_image_content_returns_false_without_throwing(): void
    {
        $service = app(ImageCompressionService::class);

        $temp = tempnam(sys_get_temp_dir(), 'comp_') . '.jpg';
        file_put_contents($temp, 'not-an-image');

        try {
            $result = $service->compressInPlace($temp);
            $this->assertFalse($result);
        } finally {
            @unlink($temp);
        }
    }
}