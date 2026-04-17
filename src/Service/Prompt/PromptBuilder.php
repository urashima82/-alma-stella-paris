<?php

declare(strict_types=1);

namespace App\Service\Prompt;

use App\Entity\CategoryVisualPrompt;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Enum\VisualType;
use App\Repository\CategoryVisualPromptRepository;

final class PromptBuilder
{
    public function __construct(
        private readonly BrandStyleProvider $brandStyleProvider,
        private readonly TechnicalSpecsProvider $technicalSpecsProvider,
        private readonly PromptFallbackProvider $fallbackProvider,
        private readonly CategoryVisualPromptRepository $categoryPromptRepository,
    ) {
    }

    public function buildForVisual(Product $product, VisualType $type): PromptResult
    {
        $category = $product->getCategory();
        $rootCategory = $this->getRootCategory($category);

        $categoryPrompt = $this->resolveCategoryPrompt($rootCategory, $type);
        $usedFallback = $categoryPrompt->getVersion() === 0;

        $blocks = [];
        $blocks[] = $this->buildMetadataBlock($product);
        $blocks[] = $this->brandStyleProvider->getBrandStyle();
        $blocks[] = $this->buildPreservationBlock($rootCategory, $category);
        $blocks[] = $this->buildFramingBlock($categoryPrompt);
        $blocks[] = $this->buildStagingBlock($categoryPrompt, $product);
        $blocks[] = $this->technicalSpecsProvider->getSpecsFor($type);

        $content = \implode("\n\n", \array_filter($blocks));

        return new PromptResult(
            content: $content,
            categoryPromptVersion: $categoryPrompt->getVersion(),
            usedFallback: $usedFallback,
        );
    }

    private function getRootCategory(?ProductCategory $category): ?ProductCategory
    {
        if ($category === null) {
            return null;
        }

        return $category->getParent() ?? $category;
    }

    private function resolveCategoryPrompt(?ProductCategory $rootCategory, VisualType $type): CategoryVisualPrompt
    {
        if ($rootCategory !== null) {
            $prompt = $this->categoryPromptRepository->findActiveForCategory($rootCategory, $type);
            if ($prompt !== null) {
                return $prompt;
            }
        }

        return $this->fallbackProvider->getGenericPromptFor($type);
    }

    private function buildMetadataBlock(Product $product): string
    {
        $lines = ['PRODUCT METADATA:'];

        $category = $product->getCategory();
        if ($category !== null) {
            $rootCategory = $this->getRootCategory($category);
            if ($rootCategory !== null && $rootCategory !== $category) {
                $lines[] = \sprintf('- Type: %s > %s', $rootCategory->getName(), $category->getName());
            } else {
                $lines[] = \sprintf('- Type: %s', $category->getName());
            }
        }

        $stones = $product->getStones();
        if (!$stones->isEmpty()) {
            $stoneNames = [];
            foreach ($stones as $stone) {
                $stoneNames[] = $stone->getName();
            }
            $lines[] = \sprintf('- Stone(s): %s', \implode(', ', $stoneNames));
            $lines[] = \sprintf('- Stone color: %s', $stones->first()->getColor());
        }

        return \implode("\n", $lines);
    }

    private function buildPreservationBlock(?ProductCategory $rootCategory, ?ProductCategory $category): string
    {
        $blocks = [];

        $preservation = $rootCategory?->getPreservationInstructions();
        if ($preservation !== null && $preservation !== '') {
            $blocks[] = "PRESERVATION REQUIREMENTS:\n".$preservation;
        }

        $specificFocus = $category?->getSpecificFocus();
        if ($specificFocus !== null && $specificFocus !== '') {
            $blocks[] = 'SPECIFIC FOCUS: '.$specificFocus;
        }

        return \implode("\n\n", $blocks);
    }

    private function buildFramingBlock(CategoryVisualPrompt $prompt): string
    {
        return "FRAMING:\n".$prompt->getFraming();
    }

    private function buildStagingBlock(CategoryVisualPrompt $prompt, Product $product): string
    {
        $lines = ['STAGING:'];
        $lines[] = '- Scene: '.$prompt->getStaging();

        $props = $prompt->getProps();
        if ($props !== []) {
            $lines[] = '- Suggested elements: '.\implode(', ', $props);
        }

        $stones = $product->getStones();
        if (!$stones->isEmpty()) {
            $firstStone = $stones->first();
            $color = $firstStone->getColor();
            if ($color !== '') {
                $lines[] = \sprintf('- Stone rendering: %s stone', $color);
            }
        }

        return \implode("\n", $lines);
    }
}
