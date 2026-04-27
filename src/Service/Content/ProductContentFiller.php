<?php

declare(strict_types=1);

namespace App\Service\Content;

use App\Entity\Product;
use App\Entity\ProductContentSuggestion;
use App\Enum\ContentSuggestionStatus;
use App\Service\Gemini\GeminiApiException;
use App\Service\Gemini\GeminiTextClient;
use App\Service\Visual\ImageStorage;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates the AI content-filling pipeline:
 *  1. Load source photos for the product
 *  2. Snapshot the taxonomy context (category, stones) for traceability
 *  3. Build the multimodal prompt + JSON response schema
 *  4. Call Gemini text client
 *  5. Parse the structured payload
 *  6. Return a hydrated ContentSuggestionResult (caller persists)
 */
final class ProductContentFiller
{
    public function __construct(
        private readonly ImageStorage $imageStorage,
        private readonly ContentPromptBuilder $promptBuilder,
        private readonly GeminiTextClient $geminiClient,
        private readonly LoggerInterface $logger,
        private readonly float $estimatedCostUsd,
    ) {
    }

    public function fill(Product $product, ?string $additionalContext = null): ContentSuggestionResult
    {
        if ($product->getSourcePhotos()->isEmpty()) {
            throw new ContentSuggestionException('Cannot generate content for a product with no source photos.');
        }

        $promptResult = $this->promptBuilder->build($product, $additionalContext);

        $sourcesBase64 = [];
        foreach ($product->getSourcePhotos() as $sourcePhoto) {
            $content = $this->imageStorage->read($sourcePhoto->getPath());
            $sourcesBase64[] = \base64_encode($content);
        }

        try {
            $response = $this->geminiClient->generate(
                $promptResult->content,
                $sourcesBase64,
                $this->promptBuilder->getResponseSchema(),
            );
        } catch (GeminiApiException $e) {
            throw new ContentSuggestionException($e->getMessage(), $e->getHttpStatusCode(), $e->getRequestId(), $e);
        }

        $payload = $response->structuredPayload;

        $this->ensureField($payload, 'nameEn');
        $this->ensureField($payload, 'nameFr');
        $this->ensureField($payload, 'descriptionEn');
        $this->ensureField($payload, 'descriptionFr');

        $this->logger->info('Content suggestion generated', [
            'productId' => $product->getId(),
            'usedFallback' => $promptResult->usedFallback,
            'requestId' => $response->requestId,
        ]);

        return new ContentSuggestionResult(
            nameEn: (string) $payload['nameEn'],
            nameFr: (string) $payload['nameFr'],
            descriptionEn: (string) $payload['descriptionEn'],
            descriptionFr: (string) $payload['descriptionFr'],
            modelName: $this->geminiClient->getModelName(),
            requestId: $response->requestId,
            estimatedCostUsd: $this->estimatedCostUsd,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function buildContextSnapshot(Product $product): array
    {
        $category = $product->getCategory();
        $categorySnapshot = null;
        if ($category !== null) {
            $parent = $category->getParent();
            $categorySnapshot = [
                'id' => $category->getId(),
                'name_fr' => $category->getNameFr(),
                'parent_name_fr' => $parent?->getNameFr(),
                'specific_focus' => $category->getSpecificFocus(),
            ];
        }

        $stoneSnapshots = [];
        foreach ($product->getStones() as $stone) {
            $stoneSnapshots[] = [
                'id' => $stone->getId(),
                'name_fr' => $stone->getNameFr(),
                'color' => $stone->getColor(),
                'virtues_fr' => \trim(\strip_tags($stone->getVirtuesFr())),
            ];
        }

        return [
            'category' => $categorySnapshot,
            'stones' => $stoneSnapshots,
        ];
    }

    /**
     * Reject any prior pending suggestions for this product before persisting a
     * fresh one. Keeps the "one pending suggestion at a time" invariant the UI
     * relies on, while preserving history (status=Rejected stays in DB).
     *
     * @param ProductContentSuggestion[] $pendingSuggestions
     */
    public function rejectPriorPending(array $pendingSuggestions): void
    {
        foreach ($pendingSuggestions as $prior) {
            $prior->setStatus(ContentSuggestionStatus::Rejected);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function ensureField(array $payload, string $field): void
    {
        if (!isset($payload[$field]) || !\is_string($payload[$field]) || \trim($payload[$field]) === '') {
            throw new ContentSuggestionException(\sprintf('Gemini response missing or empty field: %s', $field));
        }
    }
}
