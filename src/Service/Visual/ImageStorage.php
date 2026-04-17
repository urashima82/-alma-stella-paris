<?php

declare(strict_types=1);

namespace App\Service\Visual;

use App\Entity\Product;
use App\Enum\VisualType;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImageStorage
{
    public function __construct(
        private readonly FilesystemOperator $defaultStorage,
    ) {
    }

    public function storeSourcePhoto(UploadedFile $file, Product $product): string
    {
        $productId = $product->getId();
        $extension = $file->guessExtension() ?? 'jpg';
        $position = $product->getSourcePhotos()->count() + 1;
        $path = \sprintf('%d/sources/%02d.%s', $productId, $position, $extension);

        $this->defaultStorage->write($path, (string) \file_get_contents($file->getPathname()));

        return $path;
    }

    public function storeSourcePhotoFromPath(string $sourcePath, Product $product, int $position): string
    {
        $productId = $product->getId();
        $extension = \pathinfo($sourcePath, \PATHINFO_EXTENSION) ?: 'jpg';
        $path = \sprintf('%d/sources/%02d.%s', $productId, $position, $extension);

        $content = \file_get_contents($sourcePath);
        if ($content === false) {
            throw new \RuntimeException(\sprintf('Cannot read source file: %s', $sourcePath));
        }

        $this->defaultStorage->write($path, $content);

        return $path;
    }

    public function storeGeneratedVisual(string $base64Data, Product $product, VisualType $type, int $variant): string
    {
        $productId = $product->getId();
        $path = \sprintf('%d/generated/%s-v%d.webp', $productId, $type->value, $variant);

        $this->defaultStorage->write($path, \base64_decode($base64Data, true) ?: '');

        return $path;
    }

    public function read(string $path): string
    {
        return $this->defaultStorage->read($path);
    }

    public function delete(string $path): void
    {
        if ($this->defaultStorage->fileExists($path)) {
            $this->defaultStorage->delete($path);
        }
    }

    public function getPublicUrl(string $path): string
    {
        return '/storage/products/'.$path;
    }
}
