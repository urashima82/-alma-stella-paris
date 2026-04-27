<?php

declare(strict_types=1);

namespace App\Service\Generator;

use App\Enum\VisualType;
use App\Service\Gemini\GeminiApiException;
use App\Service\Gemini\GeminiImageClient;

final class GeminiVisualGenerator implements VisualGeneratorInterface
{
    public function __construct(
        private readonly GeminiImageClient $client,
        private readonly float $estimatedCostUsd,
    ) {
    }

    public function generate(
        string $prompt,
        array $sourceBase64,
        VisualType $visualType,
    ): GeneratedVisualResult {
        try {
            $response = $this->client->generate($prompt, $sourceBase64);
        } catch (GeminiApiException $e) {
            throw new VisualGenerationException($e->getMessage(), $e->getHttpStatusCode(), $e->getRequestId(), $e);
        }

        return new GeneratedVisualResult(
            imageBase64: $response->imageData,
            mimeType: $response->mimeType,
            requestId: $response->requestId ?? '',
            modelName: $this->client->getModelName(),
            estimatedCostUsd: $this->estimatedCostUsd,
        );
    }
}
