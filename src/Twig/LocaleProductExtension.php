<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Promotion;
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
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('category_path', $this->categoryPath(...)),
        ];
    }

    public function localizedName(Product|ProductCategory|Promotion $entity): string
    {
        if ($this->getLocale() === 'fr') {
            return $entity->getNameFr();
        }

        return $entity->getName();
    }

    public function localizedDescription(Product|ProductCategory $entity): string
    {
        if ($this->getLocale() === 'fr') {
            return $entity->getDescriptionFr() ?? '';
        }

        return $entity->getDescription() ?? '';
    }

    public function localizedSlug(Product|ProductCategory $entity): string
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

    private function getLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->getLocale() ?? 'en';
    }
}
