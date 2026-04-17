<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GeminiUsageLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GeminiUsageLog>
 */
class GeminiUsageLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GeminiUsageLog::class);
    }

    public function getCurrentMonthTotal(): float
    {
        $firstOfMonth = new \DateTimeImmutable('first day of this month midnight');

        return (float) $this->createQueryBuilder('l')
            ->select('COALESCE(SUM(l.costUsd), 0)')
            ->andWhere('l.createdAt >= :since')
            ->setParameter('since', $firstOfMonth)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
