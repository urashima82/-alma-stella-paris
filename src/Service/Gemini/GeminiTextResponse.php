<?php

declare(strict_types=1);

namespace App\Service\Gemini;

final readonly class GeminiTextResponse
{
    /**
     * @param array<string, mixed> $structuredPayload structured JSON returned by Gemini (parsed)
     */
    public function __construct(
        public array $structuredPayload,
        public string $rawText,
        public ?string $requestId = null,
    ) {
    }
}
