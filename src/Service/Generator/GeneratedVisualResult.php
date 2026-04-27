<?php

declare(strict_types=1);

namespace App\Service\Generator;

final readonly class GeneratedVisualResult
{
    public function __construct(
        public string $imageBase64,
        public string $mimeType,
        public string $requestId,
        public string $modelName,
        public float $estimatedCostUsd,
    ) {
    }
}
