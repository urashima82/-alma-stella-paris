<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
            ->select('SUM(o.totalUsd)')
            ->where('o.createdAt >= :monday')
            ->andWhere('o.status != :cancelled')
            ->setParameter('monday', $monday)
            ->setParameter('cancelled', OrderStatus::Cancelled)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Generate the next sequential invoice number for the given year: FA-YYYY-XXXXX.
     */
    public function nextInvoiceNumber(int $year): string
    {
        $prefix = \sprintf('FA-%d-', $year);

        $lastNumber = $this->createQueryBuilder('o')
            ->select('o.invoiceNumber')
            ->where('o.invoiceNumber LIKE :prefix')
            ->setParameter('prefix', $prefix.'%')
            ->orderBy('o.invoiceNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult();

        if (null === $lastNumber) {
            return $prefix.'00001';
        }

        $sequence = (int) \substr((string) $lastNumber, \strlen($prefix));

        return \sprintf('%s%05d', $prefix, $sequence + 1);
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
}
