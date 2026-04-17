<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CategoryVisualPrompt;
use App\Entity\ProductCategory;
use App\Enum\VisualType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryVisualPrompt>
 */
class CategoryVisualPromptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryVisualPrompt::class);
    }

    public function findActiveForCategory(ProductCategory $category, VisualType $type): ?CategoryVisualPrompt
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.category = :category')
            ->andWhere('p.visualType = :type')
            ->andWhere('p.active = true')
            ->orderBy('p.version', 'DESC')
            ->setMaxResults(1)
            ->setParameter('category', $category)
            ->setParameter('type', $type)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
