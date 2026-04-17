<?php

declare(strict_types=1);

namespace App\Service\Gemini;

final readonly class GeminiResponse
{
    public function __construct(
        public string $imageData,
        public string $mimeType,
        public ?string $requestId = null,
    ) {
    }
}
