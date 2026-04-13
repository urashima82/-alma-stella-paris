<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findActiveByProduct(Product $product): ?Reservation
    {
        return $this->createQueryBuilder('r')
            ->where('r.product = :product')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('product', $product)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Reservation[]
     */
    public function findActiveBySessionId(string $sessionId): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.sessionId = :sessionId')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * @return int[] product IDs with active reservations not owned by the given session
     */
    public function findReservedProductIdsByOthers(string $sessionId): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.product) AS productId')
            ->where('r.sessionId != :sessionId')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getScalarResult();

        return \array_map(static fn (array $row): int => (int) $row['productId'], $rows);
    }

    public function deleteExpired(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->delete()
            ->where('r.expiresAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function deleteByProduct(Product $product): void
    {
        $this->createQueryBuilder('r')
            ->delete()
            ->where('r.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->execute();
    }

    public function deleteBySessionId(string $sessionId): void
    {
        $this->createQueryBuilder('r')
            ->delete()
            ->where('r.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->execute();
    }

    /**
     * Transfer all active reservations from one session to another.
     */
    public function transferBySessionId(string $fromSessionId, string $toSessionId): int
    {
        return (int) $this->createQueryBuilder('r')
            ->update()
            ->set('r.sessionId', ':toSessionId')
            ->where('r.sessionId = :fromSessionId')
            ->andWhere('r.expiresAt > :now')
            ->setParameter('fromSessionId', $fromSessionId)
            ->setParameter('toSessionId', $toSessionId)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }
}
