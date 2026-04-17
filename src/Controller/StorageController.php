<?php

declare(strict_types=1);

namespace App\Controller;

use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class StorageController
{
    public function __construct(
        private readonly FilesystemOperator $defaultStorage,
    ) {
    }

    #[Route('/storage/products/{path}', name: 'storage_products', requirements: ['path' => '.+'], methods: ['GET'])]
    public function __invoke(string $path): Response
    {
        if (!\preg_match('#^[\w\-./]+$#', $path) || \str_contains($path, '..')) {
            throw new NotFoundHttpException();
        }

        if (!$this->defaultStorage->fileExists($path)) {
            throw new NotFoundHttpException();
        }

        $mimeType = $this->defaultStorage->mimeType($path);
        $lastModified = $this->defaultStorage->lastModified($path);

        $response = new StreamedResponse(function () use ($path): void {
            $stream = $this->defaultStorage->readStream($path);
            \fpassthru($stream);

            if (\is_resource($stream)) {
                \fclose($stream);
            }
        });

        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', 'public, max-age=86400');
        $response->setLastModified(\DateTimeImmutable::createFromFormat('U', (string) $lastModified) ?: null);

        return $response;
    }
}
