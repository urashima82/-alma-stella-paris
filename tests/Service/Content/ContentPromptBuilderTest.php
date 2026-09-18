<?php

declare(strict_types=1);

namespace App\Tests\Service\Content;

use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Stone;
use App\Repository\ProductRepository;
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

    /**
     * @param list<array{name: string, nameFr: string}> $familyNames
     */
    private function newBuilder(array $familyNames = []): ContentPromptBuilder
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('findFamilyNames')->willReturn($familyNames);

        return new ContentPromptBuilder(
            new ContentBrandVoiceProvider(),
            new ContentFewShotProvider(),
            $repository,
        );
    }

    public function testTakenNamesAreListedForTheFamily(): void
    {
        $builder = $this->newBuilder([
            ['name' => 'Marina Bangle', 'nameFr' => 'Jonc Marina'],
            ['name' => 'Elsa Bracelet', 'nameFr' => 'Bracelet Elsa'],
        ]);

        $product = new Product();
        $product->setCategory($this->newCategory('Joncs simples', $this->newCategory('Bracelets')));

        $result = $builder->build($product);

        self::assertStringContainsString('NAMES ALREADY IN USE IN THE "Bracelets" FAMILY', $result->content);
        self::assertStringContainsString('- Jonc Marina / Marina Bangle', $result->content);
        self::assertStringContainsString('- Bracelet Elsa / Elsa Bracelet', $result->content);

        // The whole name is what must be new — never the proper name on its own.
        self::assertStringContainsString('must match none of these in full', $result->content);
        self::assertStringContainsString('under a different sub-category is fine', $result->content);
    }

    public function testNoTakenNamesBlockWhenTheFamilyIsEmpty(): void
    {
        $builder = $this->newBuilder([]);

        $product = new Product();
        $product->setCategory($this->newCategory('Bracelets'));

        self::assertStringNotContainsString('ALREADY IN USE', $builder->build($product)->content);
    }

    public function testNoTakenNamesBlockWithoutACategory(): void
    {
        $builder = $this->newBuilder([
            ['name' => 'Marina Bangle', 'nameFr' => 'Jonc Marina'],
        ]);

        self::assertStringNotContainsString('ALREADY IN USE', $builder->build(new Product())->content);
    }

    public function testNamingRuleTeachesTheProperNameShape(): void
    {
        $content = $this->newBuilder()->build(new Product())->content;

        // The written rule and the few-shot names must agree, or the examples win.
        self::assertStringContainsString('the family word, then the sub-category word', $content);
        self::assertStringContainsString('Bracelet Jonc Elsa', $content);
        self::assertStringContainsString('Bague Fine Livia', $content);
        self::assertStringContainsString('Boucles Dormeuses Ostende', $content);
        self::assertStringContainsString('Collier Médaillon Ravenne', $content);
        self::assertStringContainsString('registered brands', $content);
    }

    public function testEveryFewShotNameCarriesATypeAndAProperName(): void
    {
        foreach ((new ContentFewShotProvider())->getExamples() as $example) {
            $words = \explode(' ', $example['name_fr']);

            self::assertGreaterThanOrEqual(
                3,
                \count($words),
                \sprintf('Few-shot "%s" must carry family, sub-category and proper name.', $example['name_fr']),
            );

            // The proper name closes the name and owes nothing to the taxonomy —
            // that is the whole point of the scheme.
            $properName = \end($words);
            self::assertStringNotContainsStringIgnoringCase(
                $properName,
                $example['category'],
                \sprintf('Few-shot "%s" ends on a category word, not a proper name.', $example['name_fr']),
            );
        }
    }

    private function newCategory(string $nameFr, ?ProductCategory $parent = null): ProductCategory
    {
        $category = new ProductCategory();
        $category->setNameFr($nameFr);
        $category->setName($nameFr);
        $category->setSlug(\strtolower($nameFr));
        $category->setSlugFr(\strtolower($nameFr));
        $category->setParent($parent);

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
