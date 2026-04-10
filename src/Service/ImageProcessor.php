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
    public function process(string $sourcePath, string $targetPath, int $maxWidth, int $maxHeight): void
    {
        $image = $this->manager->decodePath($sourcePath);

        $image->scaleDown($maxWidth, $maxHeight);

        $image->encode(new WebpEncoder($this->webpQuality))->save($targetPath);
    }
}
