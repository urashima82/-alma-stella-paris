<?php

declare(strict_types=1);

namespace App\Service\Content;

use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Stone;

/**
 * Builds the multimodal prompt sent to Gemini for AI content filling.
 *
 * Composes: brand voice + structural rules + few-shot examples + dynamic
 * taxonomy context (category, stones) + optional regeneration steering text.
 *
 * Implements the fallback strategy from the roadmap:
 *  - missing category → ask the model to identify the jewel type from photos
 *  - missing stones   → ask the model to describe by colour/appearance only,
 *                       not to name a specific stone (avoid mis-identification)
 *  - both missing     → purely descriptive visual mode
 */
final class ContentPromptBuilder
{
    public function __construct(
        private readonly ContentBrandVoiceProvider $brandVoice,
        private readonly ContentFewShotProvider $fewShot,
    ) {
    }

    public function build(Product $product, ?string $additionalContext = null): ContentPromptResult
    {
        $category = $product->getCategory();
        $stones = $product->getStones();

        $sections = [
            $this->brandVoice->getEditorialVoice(),
            $this->renderTaskInstructions(),
            $this->renderFallbackInstructions($category, $stones->count() > 0),
            "FEW-SHOT EXAMPLES (output style only — do not copy content):\n\n".$this->fewShot->renderForPrompt(),
            $this->renderDynamicContext($category, $stones->toArray()),
        ];

        if ($additionalContext !== null && \trim($additionalContext) !== '') {
            $sections[] = "ADDITIONAL STEERING (mandatory): {$additionalContext}";
        }

        $sections[] = 'ATTACHED IMAGES: source photos of this exact product. Look at them carefully. The output must describe THIS piece, not a generic example.';

        return new ContentPromptResult(
            content: \implode("\n\n---\n\n", $sections),
            usedFallback: $category === null || $stones->count() === 0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'nameFr' => [
                    'type' => 'STRING',
                    'description' => 'Product name in French (2 to 4 words, evocative).',
                ],
                'nameEn' => [
                    'type' => 'STRING',
                    'description' => 'Product name in English (2 to 4 words, same register as nameFr).',
                ],
                'descriptionFr' => [
                    'type' => 'STRING',
                    'description' => 'French description, two short paragraphs separated by a blank line, 50–90 words total.',
                ],
                'descriptionEn' => [
                    'type' => 'STRING',
                    'description' => 'English description, two short paragraphs separated by a blank line, 50–90 words total.',
                ],
            ],
            'required' => ['nameFr', 'nameEn', 'descriptionFr', 'descriptionEn'],
            'propertyOrdering' => ['nameFr', 'nameEn', 'descriptionFr', 'descriptionEn'],
        ];
    }

    private function renderTaskInstructions(): string
    {
        return <<<'TXT'
            TASK: Generate product copy for the jewelry piece shown in the attached photos.

            Output four fields exactly: nameFr, nameEn, descriptionFr, descriptionEn.
            Stick to the BRAND VOICE rules above for tone, length and structure.
            Do not invent technical specifications (no carat weight, no stone type unless given below).
            French content must use correct UTF-8 accents (é è à ù ê î ô û ç œ).
            TXT;
    }

    private function renderFallbackInstructions(?ProductCategory $category, bool $hasStones): string
    {
        $instructions = [];

        if ($category === null) {
            $instructions[] = 'CATEGORY UNKNOWN: identify the jewel type from the attached photos (ring, earrings, bracelet, necklace) and adapt vocabulary accordingly.';
        }

        if (!$hasStones) {
            $instructions[] = 'STONE UNKNOWN: if a stone or gem-like material is visible, describe it by its colour and appearance only (e.g. "a deep-blue stone", "an iridescent translucent stone"). Never name a specific stone — risk of mis-identification.';
        }

        if ($category === null && !$hasStones) {
            $instructions[] = 'NEITHER CATEGORY NOR STONE GIVEN: stay in purely descriptive-visual mode. Expect more generic copy than usual.';
        }

        if ($instructions === []) {
            return 'CONTEXT IS COMPLETE: use the category and stones provided below as authoritative. Do not contradict them.';
        }

        return "FALLBACK INSTRUCTIONS:\n- ".\implode("\n- ", $instructions);
    }

    /**
     * @param Stone[] $stones
     */
    private function renderDynamicContext(?ProductCategory $category, array $stones): string
    {
        $lines = ['DYNAMIC CONTEXT FOR THIS PRODUCT:'];

        if ($category !== null) {
            $parent = $category->getParent();
            $lines[] = $parent !== null
                ? \sprintf('- Category: %s > %s', $parent->getNameFr(), $category->getNameFr())
                : \sprintf('- Category: %s', $category->getNameFr());

            if ($category->getSpecificFocus() !== null && \trim($category->getSpecificFocus()) !== '') {
                $lines[] = \sprintf('- Category focus: %s', $category->getSpecificFocus());
            }
        } else {
            $lines[] = '- Category: (not provided)';
        }

        if ($stones === []) {
            $lines[] = '- Stones: (not provided)';
        } else {
            $stoneLines = [];
            foreach ($stones as $stone) {
                $stoneLines[] = \sprintf(
                    '  · %s (colour: %s; virtues: %s)',
                    $stone->getNameFr(),
                    $stone->getColor(),
                    \trim(\strip_tags($stone->getVirtuesFr())),
                );
            }
            $lines[] = '- Stones:';
            $lines = \array_merge($lines, $stoneLines);
        }

        return \implode("\n", $lines);
    }
}
