<?php
// app/Services/DocumentWatermarkService.php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentWatermarkService
{
    /**
     * Watermark a stored file. Returns the watermarked relative path or null on failure.
     */
    public function watermark(string $storedPath, string $platformName = 'Victorias Tourism'): ?string
    {
        $fullPath = Storage::disk('public')->path($storedPath);
        if (! file_exists($fullPath)) {
            return null;
        }

        $mime      = mime_content_type($fullPath);
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        $label     = "For {$platformName} Verification Only";

        $outputRelative = 'kyb-documents/watermarked/' . basename($storedPath) . '.watermarked.' . $extension;
        $outputFull     = Storage::disk('public')->path($outputRelative);

        if (! is_dir(dirname($outputFull))) {
            mkdir(dirname($outputFull), 0755, true);
        }

        try {
            if (str_starts_with($mime, 'image/')) {
                $this->watermarkImage($fullPath, $outputFull, $label, $mime);
            } elseif ($mime === 'application/pdf') {
                $this->watermarkPdf($fullPath, $outputFull, $label);
            } else {
                return null;
            }

            return $outputRelative;
        } catch (\Exception $e) {
            Log::error('Watermark failed: ' . $e->getMessage(), [
                'path' => $storedPath,
                'mime' => $mime,
            ]);
            return null;
        }
    }

    protected function watermarkImage(string $source, string $dest, string $text, string $mime): void
    {
        $image = match ($mime) {
            'image/jpeg', 'image/jpg' => imagecreatefromjpeg($source),
            'image/png'               => imagecreatefrompng($source),
            'image/webp'              => imagecreatefromwebp($source),
            default                   => null,
        };

        if (! $image) {
            throw new \RuntimeException("Unsupported image format: {$mime}");
        }

        $width  = imagesx($image);
        $height = imagesy($image);

        $fontSize  = max(3, (int) ($width / 120));
        $textWidth = imagefontwidth($fontSize) * \strlen($text);
        $spacingX  = $textWidth + 60;
        $spacingY  = imagefontheight($fontSize) + 80;

        $color = imagecolorallocatealpha($image, 200, 30, 30, 55);

        for ($y = 10; $y < $height; $y += $spacingY) {
            for ($x = -20; $x < $width; $x += $spacingX) {
                imagestring($image, $fontSize, $x, $y, $text, $color);
            }
        }

        match ($mime) {
            'image/jpeg', 'image/jpg' => imagejpeg($image, $dest, 88),
            'image/png'               => imagepng($image, $dest),
            'image/webp'              => imagewebp($image, $dest, 88),
        };
    }

    protected function watermarkPdf(string $source, string $dest, string $text): void
    {
        $fpdiClass = 'setasign\\Fpdi\\Fpdi';
        if (! class_exists($fpdiClass)) {
            throw new \RuntimeException('FPDI is not installed.');
        }

        $pdf       = new $fpdiClass();
        $pageCount = $pdf->setSourceFile($source);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size       = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            $pdf->SetFont('Helvetica', 'B', 22);
            $pdf->SetTextColor(200, 30, 30);

            // FPDF has no alpha channel. The watermark is drawn fully
            // opaque; the muted red palette keeps it readable over
            // light document backgrounds without obscuring content.

            for ($i = -2; $i <= 2; $i++) {
                $pdf->SetXY($size['width'] * 0.08, $size['height'] * 0.5 + $i * 90);
                $pdf->Cell($size['width'] * 0.84, 20, $text, 0, 0, 'C');
            }
        }

        $pdf->Output($dest, 'F');
    }
}