<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GeneratedVisual;
use App\Entity\Product;
use App\Enum\VisualStatus;
use App\Enum\VisualType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GeneratedVisual>
 */
class GeneratedVisualRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GeneratedVisual::class);
    }

    /**
     * @return array<string, GeneratedVisual[]>
     */
    public function findByProductGroupedByType(Product $product): array
    {
        /** @var GeneratedVisual[] $visuals */
        $visuals = $this->createQueryBuilder('v')
            ->andWhere('v.product = :product')
            ->orderBy('v.type', 'ASC')
            ->addOrderBy('v.variant', 'ASC')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($visuals as $visual) {
            $grouped[$visual->getType()->value][] = $visual;
        }

        return $grouped;
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        /** @var array<int, array{status: VisualStatus, count: string}> $rows */
        $rows = $this->createQueryBuilder('v')
            ->select('v.status AS status, COUNT(v.id) AS count')
            ->groupBy('v.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['status']->value] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * @return GeneratedVisual[]
     */
    public function findPendingReviewForProduct(Product $product): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.product = :product')
            ->andWhere('v.status = :status')
            ->orderBy('v.type', 'ASC')
            ->addOrderBy('v.variant', 'ASC')
            ->setParameter('product', $product)
            ->setParameter('status', VisualStatus::PendingReview)
            ->getQuery()
            ->getResult();
    }

    public function hasPendingGeneration(Product $product): bool
    {
        $count = (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.product = :product')
            ->andWhere('v.status = :status')
            ->setParameter('product', $product)
            ->setParameter('status', VisualStatus::Generating)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function hasApprovedForAllTypes(Product $product): bool
    {
        $count = (int) $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.type)')
            ->andWhere('v.product = :product')
            ->andWhere('v.status = :status')
            ->setParameter('product', $product)
            ->setParameter('status', VisualStatus::Approved)
            ->getQuery()
            ->getSingleScalarResult();

        return $count >= \count(VisualType::cases());
    }
}
