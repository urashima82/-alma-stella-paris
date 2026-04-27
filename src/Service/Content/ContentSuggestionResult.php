<?php

declare(strict_types=1);

namespace App\Service\Content;

final readonly class ContentSuggestionResult
{
    public function __construct(
        public string $nameEn,
        public string $nameFr,
        public string $descriptionEn,
        public string $descriptionFr,
        public string $modelName,
        public ?string $requestId,
        public float $estimatedCostUsd,
    ) {
    }
}
