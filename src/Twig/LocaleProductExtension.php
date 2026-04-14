<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Promotion;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class LocaleProductExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
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

    public function localizedName(Product|ProductCategory|Promotion $entity): string
    {
        if ($this->getLocale() === 'fr') {
            return $entity->getNameFr();
        }

        return $entity->getName();
    }

    public function localizedDescription(Product $product): string
    {
        if ($this->getLocale() === 'fr') {
            return $product->getDescriptionFr();
        }

        return $product->getDescription();
    }

    public function localizedSlug(Product|ProductCategory $entity): string
    {
        if ($this->getLocale() === 'fr') {
            return $entity->getSlugFr();
        }

        return $entity->getSlug();
    }

    private function getLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->getLocale() ?? 'en';
    }
}
