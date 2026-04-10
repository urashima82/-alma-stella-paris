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
}
