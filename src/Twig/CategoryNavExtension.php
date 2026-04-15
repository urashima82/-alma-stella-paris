<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\ProductCategory;
use App\Repository\ProductCategoryRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CategoryNavExtension extends AbstractExtension
{
    public function __construct(
        private readonly ProductCategoryRepository $categoryRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_categories', $this->getNavCategories(...)),
        ];
    }

    /**
     * @return ProductCategory[]
     */
    public function getNavCategories(): array
    {
        return $this->categoryRepository->findRootCategories();
    }
}
