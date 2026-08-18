<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function findByReference(string $reference): ?Order
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    /**
     * @return Order[]
     */
    public function findPendingWithPaymentIntent(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.stripePaymentIntentId IS NOT NULL')
            ->setParameter('status', OrderStatus::Pending)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countTodayOrders(): int
    {
        $today = new \DateTimeImmutable('today');

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdAt >= :today')
            ->andWhere('o.status != :cancelled')
            ->setParameter('today', $today)
            ->setParameter('cancelled', OrderStatus::Cancelled)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function revenueThisWeek(): float
    {
        $monday = new \DateTimeImmutable('monday this week');

        $result = $this->createQueryBuilder('o')
            ->select('SUM(o.totalEur)')
            ->where('o.createdAt >= :monday')
            ->andWhere('o.status != :cancelled')
            ->setParameter('monday', $monday)
            ->setParameter('cancelled', OrderStatus::Cancelled)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Generate the next sequential order reference for the given year: ASP-YYYY-XXXXX.
     *
     * Pass $lock = true (requires an open transaction) to take a pessimistic
     * write lock on the current max row, so two concurrent callers cannot
     * generate the same value and explode on the unique constraint at flush.
     */
    public function nextOrderReference(int $year, bool $lock = false): string
    {
        $prefix = \sprintf('ASP-%d-', $year);

        // getOneOrNullResult, not getSingleScalarResult: the latter THROWS on
        // an empty table — i.e. on the shop's very first order.
        $query = $this->createQueryBuilder('o')
            ->select('o.reference')
            ->where('o.reference LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->orderBy('o.reference', 'DESC')
            ->setMaxResults(1)
            ->getQuery();

        if ($lock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        $lastReference = $query->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);

        if ($lastReference === null) {
            return $prefix.'00001';
        }

        $sequence = (int) \substr((string) $lastReference, \strlen($prefix));

        return \sprintf('%s%05d', $prefix, $sequence + 1);
    }

    /**
     * Generate the next sequential invoice number for the given year: FA-YYYY-XXXXX.
     *
     * Same locking contract as nextOrderReference().
     */
    public function nextInvoiceNumber(int $year, bool $lock = false): string
    {
        $prefix = \sprintf('FA-%d-', $year);

        // Same as nextOrderReference: null on the year's first invoice, never throw.
        $query = $this->createQueryBuilder('o')
            ->select('o.invoiceNumber')
            ->where('o.invoiceNumber LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->orderBy('o.invoiceNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery();

        if ($lock) {
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        $lastNumber = $query->getOneOrNullResult(AbstractQuery::HYDRATE_SINGLE_SCALAR);

        if ($lastNumber === null) {
            return $prefix.'00001';
        }

        $sequence = (int) \substr((string) $lastNumber, \strlen($prefix));

        return \sprintf('%s%05d', $prefix, $sequence + 1);
    }

    /**
     * @return Order[]
     */
    public function findEligibleForTestimonialRequest(int $daysAfterPayment, int $maxDaysWindow): array
    {
        $since = new \DateTimeImmutable(\sprintf('-%d days', $maxDaysWindow));
        $cutoff = new \DateTimeImmutable(\sprintf('-%d days', $daysAfterPayment));

        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.paidAt IS NOT NULL')
            ->andWhere('o.paidAt >= :since')
            ->andWhere('o.paidAt <= :cutoff')
            ->setParameter('status', OrderStatus::Delivered)
            ->setParameter('since', $since)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('o.paidAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Order[]
     */
    public function findAbandonedPending(\DateTimeImmutable $olderThan): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.status = :status')
            ->andWhere('o.stripePaymentIntentId IS NULL')
            ->andWhere('o.createdAt < :cutoff')
            ->setParameter('status', OrderStatus::Pending)
            ->setParameter('cutoff', $olderThan)
            ->orderBy('o.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(OrderStatus $status): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function revenueLastWeek(): float
    {
        $thisMonday = new \DateTimeImmutable('monday this week');
        $lastMonday = $thisMonday->modify('-7 days');

        $result = $this->createQueryBuilder('o')
            ->select('SUM(o.totalEur)')
            ->where('o.createdAt >= :start')
            ->andWhere('o.createdAt < :end')
            ->andWhere('o.status != :cancelled')
            ->setParameter('start', $lastMonday)
            ->setParameter('end', $thisMonday)
            ->setParameter('cancelled', OrderStatus::Cancelled)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function countOrdersSameDayLastWeek(): int
    {
        $lastWeekSameDay = new \DateTimeImmutable('-7 days midnight');
        $lastWeekSameDayEnd = $lastWeekSameDay->modify('+1 day');

        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.createdAt >= :start')
            ->andWhere('o.createdAt < :end')
            ->andWhere('o.status != :cancelled')
            ->setParameter('start', $lastWeekSameDay)
            ->setParameter('end', $lastWeekSameDayEnd)
            ->setParameter('cancelled', OrderStatus::Cancelled)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countShippedWithoutTracking(): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.status = :status')
            ->andWhere('o.trackingNumber IS NULL OR o.trackingNumber = :empty')
            ->setParameter('status', OrderStatus::Shipped)
            ->setParameter('empty', '')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Order[]
     */
    public function findLatest(int $limit = 5): array
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
