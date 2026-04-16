<?php

declare(strict_types=1);

namespace App\Service;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class ImageProcessor
{
    private readonly ImageManager $manager;

    public function __construct(
        private readonly int $webpQuality = 85,
    ) {
        $this->manager = new ImageManager(Driver::class);
    }

    /**
     * Resize an image and convert it to WebP.
     */
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    public function process(string $sourcePath, string $targetPath, int $maxWidth, int $maxHeight): void
    {
        $mimeType = \mime_content_type($sourcePath);

        if ($mimeType === false || !\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \RuntimeException(\sprintf('Unsupported image type: %s', $mimeType ?: 'unknown'));
        }

        $image = $this->manager->decodePath($sourcePath);

        $image->scaleDown($maxWidth, $maxHeight);

        $image->encode(new WebpEncoder($this->webpQuality))->save($targetPath);
    }

    /**
     * Crop an image to a target aspect ratio (center crop), resize, and convert to WebP.
     */
    public function processWithCrop(string $sourcePath, string $targetPath, int $maxWidth, int $maxHeight): void
    {
        $mimeType = \mime_content_type($sourcePath);

        if ($mimeType === false || !\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new \RuntimeException(\sprintf('Unsupported image type: %s', $mimeType ?: 'unknown'));
        }

        $image = $this->manager->decodePath($sourcePath);

        // Auto-orient (EXIF rotation) then cover-crop to the target aspect ratio
        $image->orient();
        $image->coverDown($maxWidth, $maxHeight);

        $image->encode(new WebpEncoder($this->webpQuality))->save($targetPath);
    }
}
