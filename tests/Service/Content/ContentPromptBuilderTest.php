<?php

declare(strict_types=1);

namespace App\Tests\Service\Content;

use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Stone;
use App\Service\Content\ContentBrandVoiceProvider;
use App\Service\Content\ContentFewShotProvider;
use App\Service\Content\ContentPromptBuilder;
use PHPUnit\Framework\TestCase;

final class ContentPromptBuilderTest extends TestCase
{
    public function testFullContextDoesNotMarkFallback(): void
    {
        $builder = $this->newBuilder();

        $product = new Product();
        $product->setCategory($this->newCategory('Bagues'));
        $product->addStone($this->newStone('Pierre de Lune', 'Blanc nacré', 'Intuition'));

        $result = $builder->build($product);

        self::assertFalse($result->usedFallback);
        self::assertStringContainsString('Bagues', $result->content);
        self::assertStringContainsString('Pierre de Lune', $result->content);
        self::assertStringContainsString('CONTEXT IS COMPLETE', $result->content);
    }

    public function testMissingCategoryActivatesFallback(): void
    {
        $builder = $this->newBuilder();

        $product = new Product();
        $product->addStone($this->newStone('Lapis Lazuli', 'Bleu nuit', 'Vérité'));

        $result = $builder->build($product);

        self::assertTrue($result->usedFallback);
        self::assertStringContainsString('CATEGORY UNKNOWN', $result->content);
        self::assertStringContainsString('identify the jewel type from the attached photos', $result->content);
    }

    public function testMissingStoneTriggersDescriptiveFallback(): void
    {
        $builder = $this->newBuilder();

        $product = new Product();
        $product->setCategory($this->newCategory('Bracelets'));

        $result = $builder->build($product);

        self::assertTrue($result->usedFallback);
        self::assertStringContainsString('STONE UNKNOWN', $result->content);
        self::assertStringContainsString('Never name a specific stone', $result->content);
    }

    public function testBothMissingActivatesPurelyDescriptiveMode(): void
    {
        $builder = $this->newBuilder();

        $product = new Product();

        $result = $builder->build($product);

        self::assertTrue($result->usedFallback);
        self::assertStringContainsString('NEITHER CATEGORY NOR STONE GIVEN', $result->content);
    }

    public function testAdditionalContextIsAppendedAsMandatorySteering(): void
    {
        $builder = $this->newBuilder();
        $product = new Product();
        $product->setCategory($this->newCategory('Colliers'));

        $result = $builder->build($product, 'plus poétique, insiste sur la couleur bleue');

        self::assertStringContainsString('ADDITIONAL STEERING (mandatory)', $result->content);
        self::assertStringContainsString('plus poétique', $result->content);
    }

    public function testResponseSchemaForcesFourFields(): void
    {
        $builder = $this->newBuilder();
        $schema = $builder->getResponseSchema();

        self::assertSame('OBJECT', $schema['type']);
        self::assertEqualsCanonicalizing(
            ['nameFr', 'nameEn', 'descriptionFr', 'descriptionEn'],
            $schema['required']
        );
    }

    private function newBuilder(): ContentPromptBuilder
    {
        return new ContentPromptBuilder(
            new ContentBrandVoiceProvider(),
            new ContentFewShotProvider(),
        );
    }

    private function newCategory(string $nameFr): ProductCategory
    {
        $category = new ProductCategory();
        $category->setNameFr($nameFr);
        $category->setName($nameFr);
        $category->setSlug(\strtolower($nameFr));
        $category->setSlugFr(\strtolower($nameFr));

        return $category;
    }

    private function newStone(string $nameFr, string $color, string $virtuesFr): Stone
    {
        $stone = new Stone();
        $stone->setNameFr($nameFr);
        $stone->setName($nameFr);
        $stone->setSlug(\strtolower(\str_replace(' ', '-', $nameFr)));
        $stone->setSlugFr(\strtolower(\str_replace(' ', '-', $nameFr)));
        $stone->setShortDescription('—');
        $stone->setShortDescriptionFr('—');
        $stone->setDescription('—');
        $stone->setDescriptionFr('—');
        $stone->setVirtues($virtuesFr);
        $stone->setVirtuesFr($virtuesFr);
        $stone->setColor($color);

        return $stone;
    }
}
