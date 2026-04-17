<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MessengerMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MessengerMessage>
 */
class MessengerMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MessengerMessage::class);
    }

    /**
     * @return array<string, int>
     */
    public function countByQueue(): array
    {
        /** @var list<array{queueName: string, total: string}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('m.queueName, COUNT(m.id) AS total')
            ->groupBy('m.queueName')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['queueName']] = (int) $row['total'];
        }

        return $result;
    }

    public function countByQueueName(string $queueName): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.queueName = :queue')
            ->setParameter('queue', $queueName)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.deliveredAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
