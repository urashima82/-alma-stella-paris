<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Service\OrderMailer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class OrderStatusSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly OrderMailer $orderMailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityUpdatedEvent::class => 'onOrderUpdate',
        ];
    }

    /** @param BeforeEntityUpdatedEvent<object> $event */
    public function onOrderUpdate(BeforeEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (!$entity instanceof Order) {
            return;
        }

        $unitOfWork = $this->entityManager->getUnitOfWork();
        $originalData = $unitOfWork->getOriginalEntityData($entity);

        if ([] === $originalData) {
            return;
        }

        $oldStatus = $originalData['status'] ?? null;
        $newStatus = $entity->getStatus();

        if (!$oldStatus instanceof OrderStatus) {
            return;
        }

        if ($oldStatus === $newStatus) {
            return;
        }

        if (OrderStatus::Shipped === $newStatus) {
            $this->sendShippedEmail($entity);
        }
    }

    private function sendShippedEmail(Order $order): void
    {
        try {
            $this->orderMailer->sendShippedNotification($order, 'en');
            $this->logger->info('Shipped notification sent for order {reference}', [
                'reference' => $order->getReference(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send shipped notification for order {reference}: {error}', [
                'reference' => $order->getReference(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
