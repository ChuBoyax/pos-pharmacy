<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Shrinks uploaded photos so product images stay small: fixes phone camera
 * rotation, scales the longest side down to $maxSide, and re-encodes as WebP.
 */
class ImageOptimizer
{
    public function __construct(
        private ImageStore $images,
        private int $maxSide = 800,
        private int $quality = 80,
    ) {}

    /**
     * @return string the stored reference (see ImageStore)
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $image) {
            throw ValidationException::withMessages(['image' => 'This image could not be read. Please try another file.']);
        }

        $image = $this->scaleDown($this->fixOrientation($image, $file), $this->maxSide);

        // Keep transparency from PNG / WebP sources.
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, $this->quality);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $this->images->put($bytes, $directory, 'webp', 'image/webp');
    }

    private function scaleDown(GdImage $image, int $maxSide): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) <= $maxSide) {
            return $image;
        }

        $ratio = $maxSide / max($width, $height);
        $resized = imagecreatetruecolor((int) round($width * $ratio), (int) round($height * $ratio));
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, imagecolorallocatealpha($resized, 0, 0, 0, 127));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);
        imagedestroy($image);

        return $resized;
    }

    /**
     * Phone cameras save JPEGs sideways and record the rotation in EXIF.
     */
    private function fixOrientation(GdImage $image, UploadedFile $file): GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $angle = match ((int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle ? imagerotate($image, $angle, 0) : $image;
    }
}
