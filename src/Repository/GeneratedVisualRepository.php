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
     * Returns visuals grouped by type for display in the workspace.
     * Rejected visuals are excluded — they remain in the database for traceability
     * but are never shown in the UI.
     *
     * @return array<string, GeneratedVisual[]>
     */
    public function findByProductGroupedByType(Product $product): array
    {
        /** @var GeneratedVisual[] $visuals */
        $visuals = $this->createQueryBuilder('v')
            ->andWhere('v.product = :product')
            ->andWhere('v.status != :rejected')
            ->orderBy('v.type', 'ASC')
            ->addOrderBy('v.variant', 'ASC')
            ->setParameter('product', $product)
            ->setParameter('rejected', VisualStatus::Rejected)
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($visuals as $visual) {
            $grouped[$visual->getType()->value][] = $visual;
        }

        return $grouped;
    }

    /**
     * Find Approved visuals for a given (product, type) pair, optionally
     * excluding one. Used to auto-reject the previously published visual when
     * a new one is approved.
     *
     * @return GeneratedVisual[]
     */
    public function findApprovedFor(Product $product, VisualType $type, ?GeneratedVisual $exclude = null): array
    {
        $qb = $this->createQueryBuilder('v')
            ->andWhere('v.product = :product')
            ->andWhere('v.type = :type')
            ->andWhere('v.status = :status')
            ->setParameter('product', $product)
            ->setParameter('type', $type)
            ->setParameter('status', VisualStatus::Approved);

        if ($exclude !== null) {
            $qb->andWhere('v.id != :excludeId')
                ->setParameter('excludeId', $exclude->getId());
        }

        /** @var GeneratedVisual[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
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

    /**
     * Mark every Failed visual for (product, type) as Rejected, so they get
     * filtered out of the workspace UI. Called whenever a fresh generation is
     * launched or a new visual is approved — failed past attempts become
     * irrelevant once a new outcome exists for that type.
     *
     * Returns the number of rows affected. Caller is responsible for flushing.
     */
    public function markFailedAsRejected(Product $product, VisualType $type): int
    {
        /** @var GeneratedVisual[] $failed */
        $failed = $this->createQueryBuilder('v')
            ->andWhere('v.product = :product')
            ->andWhere('v.type = :type')
            ->andWhere('v.status = :status')
            ->setParameter('product', $product)
            ->setParameter('type', $type)
            ->setParameter('status', VisualStatus::Failed)
            ->getQuery()
            ->getResult();

        foreach ($failed as $visual) {
            $visual->setStatus(VisualStatus::Rejected);
        }

        return \count($failed);
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
