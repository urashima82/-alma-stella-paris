<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProductCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductCategory>
 */
class ProductCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductCategory::class);
    }

    /**
     * Returns categories in tree order: root categories first (by position),
     * each followed by its children (by position).
     *
     * @return ProductCategory[]
     */
    public function findAllOrdered(): array
    {
        $all = $this->createQueryBuilder('c')
            ->leftJoin('c.parent', 'p')
            ->addSelect('p')
            ->leftJoin('c.children', 'ch')
            ->addSelect('ch')
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();

        $roots = [];
        foreach ($all as $category) {
            if ($category->isRoot()) {
                $roots[] = $category;
            }
        }

        \usort($roots, static fn (ProductCategory $a, ProductCategory $b): int => $a->getPosition() <=> $b->getPosition());

        $tree = [];
        foreach ($roots as $root) {
            $tree[] = $root;
            $children = $root->getChildren()->toArray();
            \usort($children, static fn (ProductCategory $a, ProductCategory $b): int => $a->getPosition() <=> $b->getPosition());
            foreach ($children as $child) {
                $tree[] = $child;
            }
        }

        return $tree;
    }

    /**
     * @return ProductCategory[]
     */
    public function findRootCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'ch')
            ->addSelect('ch')
            ->where('c.parent IS NULL')
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ProductCategory[]
     */
    public function findChildrenByParent(ProductCategory $parent): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent = :parent')
            ->setParameter('parent', $parent)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ProductCategory[] leaf categories only (no children), for product assignment
     */
    public function findLeafCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'ch')
            ->leftJoin('c.parent', 'p')
            ->addSelect('p')
            ->groupBy('c.id')
            ->having('COUNT(ch.id) = 0')
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBySlug(string $slug, string $locale): ?ProductCategory
    {
        $field = $locale === 'fr' ? 'c.slugFr' : 'c.slug';

        return $this->createQueryBuilder('c')
            ->where($field.' = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
