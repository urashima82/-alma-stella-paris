<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Repository\ProductRepository;
use App\Service\ProductSlugger;
use PHPUnit\Framework\TestCase;

final class ProductSluggerTest extends TestCase
{
    public function testReturnsThePlainSlugWhenNothingClaimsIt(): void
    {
        $slugger = new ProductSlugger($this->repositoryWithTakenSlugs([]));

        self::assertSame('amber-duality-bangle', $slugger->uniqueSlug('Amber Duality Bangle'));
    }

    public function testSuffixesUntilTheSlugIsFree(): void
    {
        $slugger = new ProductSlugger($this->repositoryWithTakenSlugs([
            'amber-duality-bangle',
            'amber-duality-bangle-2',
        ]));

        self::assertSame('amber-duality-bangle-3', $slugger->uniqueSlug('Amber Duality Bangle'));
    }

    public function testFrenchSlugsUseTheirOwnNamespace(): void
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('slugExists')->willReturn(true);
        $repository->method('slugFrExists')->willReturn(false);

        $slugger = new ProductSlugger($repository);

        // The English column being full must not push the French slug to a suffix.
        self::assertSame('jonc-ambre', $slugger->uniqueSlugFr('Jonc Ambre'));
    }

    public function testExcludesTheProductBeingEdited(): void
    {
        $repository = $this->createMock(ProductRepository::class);
        $repository->expects(self::once())
            ->method('slugExists')
            ->with('amber-duality-bangle', 42)
            ->willReturn(false);

        $slugger = new ProductSlugger($repository);

        self::assertSame('amber-duality-bangle', $slugger->uniqueSlug('Amber Duality Bangle', 42));
    }

    public function testFallsBackWhenTheNameSlugifiesToNothing(): void
    {
        $slugger = new ProductSlugger($this->repositoryWithTakenSlugs([]));

        self::assertSame('produit', $slugger->uniqueSlug('✦ ✦ ✦'));
    }

    public function testNeverExceedsTheColumnLength(): void
    {
        $slugger = new ProductSlugger($this->repositoryWithTakenSlugs([
            \str_repeat('a', 280),
        ]));

        $slug = $slugger->uniqueSlug(\str_repeat('a', 400));

        self::assertLessThanOrEqual(280, \strlen($slug));
        self::assertStringEndsWith('-2', $slug);
    }

    /**
     * @param list<string> $taken
     */
    private function repositoryWithTakenSlugs(array $taken): ProductRepository
    {
        $repository = $this->createStub(ProductRepository::class);
        $repository->method('slugExists')
            ->willReturnCallback(static fn (string $slug): bool => \in_array($slug, $taken, true));
        $repository->method('slugFrExists')
            ->willReturnCallback(static fn (string $slug): bool => \in_array($slug, $taken, true));

        return $repository;
    }
}
