<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Promotion;
use App\Entity\Stone;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class LocaleProductExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('localized_name', $this->localizedName(...)),
            new TwigFilter('localized_description', $this->localizedDescription(...)),
            new TwigFilter('localized_slug', $this->localizedSlug(...)),
            new TwigFilter('localized_short_description', $this->localizedShortDescription(...)),
            new TwigFilter('localized_virtues', $this->localizedVirtues(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('category_path', $this->categoryPath(...)),
            new TwigFunction('stone_path', $this->stonePath(...)),
        ];
    }

    public function localizedName(Product|ProductCategory|Promotion|Stone $entity): string
    {
        if ($this->getLocale() === 'fr') {
            return $entity->getNameFr();
        }

        return $entity->getName();
    }

    public function localizedDescription(Product|ProductCategory|Stone $entity): string
    {
        if ($this->getLocale() === 'fr') {
            return $entity->getDescriptionFr() ?? '';
        }

        return $entity->getDescription() ?? '';
    }

    public function localizedSlug(Product|ProductCategory|Stone $entity): string
    {
        if ($this->getLocale() === 'fr') {
            return $entity->getSlugFr();
        }

        return $entity->getSlug();
    }

    /**
     * Generate the correct URL for a category, handling hierarchy.
     */
    public function categoryPath(ProductCategory $category): string
    {
        $isFr = $this->getLocale() === 'fr';

        if ($category->getParent() !== null) {
            $parent = $category->getParent();

            return $this->urlGenerator->generate('shop_catalog', [
                'parentSlug' => $isFr ? $parent->getSlugFr() : $parent->getSlug(),
                'childSlug' => $isFr ? $category->getSlugFr() : $category->getSlug(),
            ]);
        }

        return $this->urlGenerator->generate('shop_catalog', [
            'parentSlug' => $isFr ? $category->getSlugFr() : $category->getSlug(),
        ]);
    }

    public function localizedShortDescription(Stone $stone): string
    {
        if ($this->getLocale() === 'fr') {
            return $stone->getShortDescriptionFr();
        }

        return $stone->getShortDescription();
    }

    public function localizedVirtues(Stone $stone): string
    {
        if ($this->getLocale() === 'fr') {
            return $stone->getVirtuesFr();
        }

        return $stone->getVirtues();
    }

    public function stonePath(Stone $stone): string
    {
        $isFr = $this->getLocale() === 'fr';

        return $this->urlGenerator->generate('shop_stone_show', [
            'slug' => $isFr ? $stone->getSlugFr() : $stone->getSlug(),
        ]);
    }

    private function getLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->getLocale() ?? 'en';
    }
}
