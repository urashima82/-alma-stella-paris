<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return Product[]
     */
    /**
     * @return Product[]
     */
    public function findFeatured(int $limit = 4, ?string $collection = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.isFeatured = true')
            ->andWhere('p.isSoldOut = false')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        if (null !== $collection && 'all' !== $collection) {
            $qb->andWhere('JSON_CONTAINS(p.availableIn, :collection) = 1')
                ->setParameter('collection', \sprintf('"%s"', $collection));
        }

        return $qb->getQuery()->getResult();
    }

    public function countRecentlySold(int $days = 7): int
    {
        $since = new \DateTimeImmutable(\sprintf('-%d days', $days));

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isSoldOut = true')
            ->andWhere('p.soldAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAvailable(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.isPublished = true')
            ->andWhere('p.isSoldOut = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findVisibleQuery(?ProductCategory $category = null, ?string $collection = null): QueryBuilder
    {
        $soldOutCutoff = new \DateTimeImmutable('-14 days');

        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isPublished = true')
            ->andWhere('p.isSoldOut = false OR (p.isSoldOut = true AND p.soldAt > :soldOutCutoff)')
            ->setParameter('soldOutCutoff', $soldOutCutoff)
            ->orderBy('p.isSoldOut', 'ASC')
            ->addOrderBy('p.createdAt', 'DESC');

        if (null !== $category) {
            $qb->andWhere('p.category = :category')
                ->setParameter('category', $category);
        }

        if (null !== $collection && 'all' !== $collection) {
            $qb->andWhere('JSON_CONTAINS(p.availableIn, :collection) = 1')
                ->setParameter('collection', \sprintf('"%s"', $collection));
        }

        return $qb;
    }
}
