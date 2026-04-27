<?php

declare(strict_types=1);

namespace App\Tests\Service\Content;

use App\Service\Content\ContentFewShotProvider;
use PHPUnit\Framework\TestCase;

final class ContentFewShotProviderTest extends TestCase
{
    public function testReturnsAtLeastThreeExamplesAcrossCategories(): void
    {
        $provider = new ContentFewShotProvider();
        $examples = $provider->getExamples();

        self::assertGreaterThanOrEqual(3, \count($examples));

        $categories = \array_unique(\array_column($examples, 'category'));
        self::assertGreaterThanOrEqual(3, \count($categories), 'Few-shot should span multiple categories');
    }

    public function testRenderIncludesAllExamplesAsValidJsonBlocks(): void
    {
        $provider = new ContentFewShotProvider();
        $rendered = $provider->renderForPrompt();

        foreach ($provider->getExamples() as $i => $example) {
            self::assertStringContainsString('EXAMPLE '.($i + 1), $rendered);
            self::assertStringContainsString($example['category'], $rendered);
        }
    }

    public function testIncludesAtLeastOneExampleWithoutNamedStone(): void
    {
        $provider = new ContentFewShotProvider();
        $hasUnnamedStoneExample = false;
        foreach ($provider->getExamples() as $example) {
            if ($example['stones'] === '') {
                $hasUnnamedStoneExample = true;
                break;
            }
        }
        self::assertTrue(
            $hasUnnamedStoneExample,
            'At least one few-shot example must omit stones, so the model learns the no-stone fallback voice.'
        );
    }
}
