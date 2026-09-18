<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ProductRepository;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Builds product slugs that are free to persist.
 *
 * Names written by the AI content pipeline are not curated for uniqueness — two
 * similar pieces routinely come back with the same proposed name — and nobody
 * gets a chance to arbitrate, so a collision there must resolve itself rather
 * than reach the unique index as a 500. Slugs typed by hand go through
 * UniqueEntity on the entity instead: a deliberate choice deserves an error,
 * not a silent rename.
 */
final class ProductSlugger
{
    /** Matches the length of Product::$slug and Product::$slugFr. */
    private const int MAX_LENGTH = 280;

    /** Beyond this the catalogue has a naming problem, not a slug problem. */
    private const int MAX_ATTEMPTS = 100;

    private const string FALLBACK_BASE = 'produit';

    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * @param int|null $excludeId product being edited, so it does not clash with itself
     */
    public function uniqueSlug(string $name, ?int $excludeId = null): string
    {
        return $this->uniquify(
            (string) (new AsciiSlugger())->slug($name)->lower(),
            fn (string $candidate): bool => $this->productRepository->slugExists($candidate, $excludeId),
        );
    }

    /**
     * @param int|null $excludeId product being edited, so it does not clash with itself
     */
    public function uniqueSlugFr(string $nameFr, ?int $excludeId = null): string
    {
        return $this->uniquify(
            (string) (new AsciiSlugger('fr'))->slug($nameFr)->lower(),
            fn (string $candidate): bool => $this->productRepository->slugFrExists($candidate, $excludeId),
        );
    }

    /**
     * @param callable(string): bool $isTaken
     */
    private function uniquify(string $base, callable $isTaken): string
    {
        $base = $this->trim($base, self::MAX_LENGTH);

        if ($base === '') {
            $base = self::FALLBACK_BASE;
        }

        if (!$isTaken($base)) {
            return $base;
        }

        for ($suffix = 2; $suffix <= self::MAX_ATTEMPTS; ++$suffix) {
            $tail = '-'.$suffix;
            $candidate = $this->trim($base, self::MAX_LENGTH - \strlen($tail)).$tail;

            if (!$isTaken($candidate)) {
                return $candidate;
            }
        }

        // Unreachable in practice, but a random tail still beats an exception.
        $tail = '-'.\bin2hex(\random_bytes(4));

        return $this->trim($base, self::MAX_LENGTH - \strlen($tail)).$tail;
    }

    /**
     * Truncating mid-word can leave a dangling separator, which makes for an ugly URL.
     */
    private function trim(string $slug, int $maxLength): string
    {
        return \rtrim(\substr($slug, 0, $maxLength), '-');
    }
}
