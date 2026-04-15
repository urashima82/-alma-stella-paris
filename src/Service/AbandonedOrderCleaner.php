<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class AbandonedOrderCleaner
{
    private const ABANDONMENT_THRESHOLD_HOURS = 24;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Delete pending orders without a Stripe PaymentIntent that are older than the threshold.
     *
     * @return array{deleted: int}
     */
    public function cleanAll(): array
    {
        $result = ['deleted' => 0];

        $cutoff = new \DateTimeImmutable(\sprintf('-%d hours', self::ABANDONMENT_THRESHOLD_HOURS));
        $abandonedOrders = $this->orderRepository->findAbandonedPending($cutoff);

        foreach ($abandonedOrders as $order) {
            $this->logger->info('Abandoned order {ref} deleted (no payment initiated, created {date}).', [
                'ref' => $order->getReference(),
                'date' => $order->getCreatedAt()->format('Y-m-d H:i'),
            ]);

            $this->entityManager->remove($order);
            ++$result['deleted'];
        }

        if ($result['deleted'] > 0) {
            $this->entityManager->flush();
        }

        return $result;
    }
}
