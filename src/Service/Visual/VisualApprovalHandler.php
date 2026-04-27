<?php

declare(strict_types=1);

namespace App\Service\Visual;

use App\Entity\GeneratedVisual;
use App\Entity\Product;
use App\Enum\VisualStatus;
use App\Enum\VisualType;
use App\Repository\GeneratedVisualRepository;
use App\Service\ImageProcessor;

final class VisualApprovalHandler
{
    private const int THUMBNAIL_MAX_WIDTH = 600;
    private const int THUMBNAIL_MAX_HEIGHT = 750;
    private const int PHOTO_MAX_WIDTH = 800;
    private const int PHOTO_MAX_HEIGHT = 1000;

    public function __construct(
        private readonly ImageStorage $imageStorage,
        private readonly ImageProcessor $imageProcessor,
        private readonly GeneratedVisualRepository $visualRepository,
        private readonly string $uploadDir,
    ) {
    }

    /**
     * Approve a visual: auto-reject any previously approved visual for the
     * same (product, type), then mark this one as Approved and copy it to
     * the product's published image slot. Caller is responsible for flushing.
     */
    public function approve(GeneratedVisual $visual): void
    {
        $product = $visual->getProduct();
        $type = $visual->getType();

        if ($product === null) {
            throw new \RuntimeException('Cannot approve a visual without a product.');
        }

        foreach ($this->visualRepository->findApprovedFor($product, $type, $visual) as $previous) {
            $previous->setStatus(VisualStatus::Rejected);
        }

        // Failed attempts of the same type are now obsolete: an outcome exists.
        $this->visualRepository->markFailedAsRejected($product, $type);

        $visual->setStatus(VisualStatus::Approved);
        $this->copyToProductImages($visual);
    }

    public function copyToProductImages(GeneratedVisual $visual): void
    {
        $product = $visual->getProduct();
        if ($product === null) {
            throw new \RuntimeException('Cannot approve a visual without a product.');
        }

        $imageData = $this->imageStorage->read($visual->getPath());

        $tempSource = \tempnam(\sys_get_temp_dir(), 'alma_visual_');
        if ($tempSource === false) {
            throw new \RuntimeException('Failed to create temporary file.');
        }

        try {
            \file_put_contents($tempSource, $imageData);

            $filename = $this->generateFilename($product, $visual->getType());
            $targetPath = $this->uploadDir.'/'.$filename;

            [$maxWidth, $maxHeight] = $this->getDimensionsForType($visual->getType());
            $this->imageProcessor->processWithCrop($tempSource, $targetPath, $maxWidth, $maxHeight);

            $this->setProductField($product, $visual->getType(), $filename);
        } finally {
            if (\file_exists($tempSource)) {
                \unlink($tempSource);
            }
        }
    }

    private function generateFilename(Product $product, VisualType $type): string
    {
        return \sprintf(
            '%d-%s-%s.webp',
            $product->getId(),
            $type->value,
            \bin2hex(\random_bytes(4)),
        );
    }

    /**
     * @return array{int, int}
     */
    private function getDimensionsForType(VisualType $type): array
    {
        return match ($type) {
            VisualType::Vignette => [self::THUMBNAIL_MAX_WIDTH, self::THUMBNAIL_MAX_HEIGHT],
            VisualType::Worn, VisualType::Lifestyle => [self::PHOTO_MAX_WIDTH, self::PHOTO_MAX_HEIGHT],
        };
    }

    private function setProductField(Product $product, VisualType $type, string $filename): void
    {
        match ($type) {
            VisualType::Vignette => $product->setThumbnail($filename),
            VisualType::Worn => $product->setWornPhoto($filename),
            VisualType::Lifestyle => $product->setContextPhoto($filename),
        };
    }
}
