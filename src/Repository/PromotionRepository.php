<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Promotion;
use App\Enum\PromotionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Promotion>
 */
class PromotionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Promotion::class);
    }

    public function findByCode(string $code): ?Promotion
    {
        return $this->findOneBy(['code' => \strtoupper(\trim($code))]);
    }

    /**
     * @return Promotion[]
     */
    public function findActiveAutoProductPromotions(): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('p')
            ->where('p.type = :type')
            ->andWhere('p.isActive = true')
            ->andWhere('p.startsAt IS NULL OR p.startsAt <= :now')
            ->andWhere('p.endsAt IS NULL OR p.endsAt >= :now')
            ->setParameter('type', PromotionType::ProductAutomatic)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Promotion[]
     */
    public function findActiveAutoCartPromotions(): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('p')
            ->where('p.type = :type')
            ->andWhere('p.isActive = true')
            ->andWhere('p.startsAt IS NULL OR p.startsAt <= :now')
            ->andWhere('p.endsAt IS NULL OR p.endsAt >= :now')
            ->setParameter('type', PromotionType::CartAutomatic)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    public function countUsagesByEmail(Promotion $promotion, string $email): int
    {
        return (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(\App\Entity\PromotionUsage::class, 'u')
            ->where('u.promotion = :promotion')
            ->andWhere('u.customerEmail = :email')
            ->setParameter('promotion', $promotion)
            ->setParameter('email', $email)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
