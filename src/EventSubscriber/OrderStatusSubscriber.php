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
use Symfony\Component\HttpFoundation\RequestStack;

final class OrderStatusSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly OrderMailer $orderMailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly RequestStack $requestStack,
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

        if ($originalData === []) {
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

        if ($newStatus === OrderStatus::Processing) {
            $this->sendAdminNotification($entity);
        }

        if ($newStatus === OrderStatus::Shipped) {
            if ($entity->getTrackingNumber() === null || \trim($entity->getTrackingNumber()) === '') {
                $this->addFlash('warning', 'Impossible de passer en « Expédiée » sans numéro de suivi.');
                $entity->setStatus($oldStatus);

                return;
            }

            $this->sendNotification($entity, 'shipped');
        }

        if ($newStatus === OrderStatus::Delivered) {
            $this->sendNotification($entity, 'delivered');
        }

        if ($newStatus === OrderStatus::Cancelled) {
            $this->sendNotification($entity, 'cancelled');
        }
    }

    private function sendNotification(Order $order, string $type): void
    {
        $locale = $order->getCustomerLocale() ?? 'en';
        $labels = [
            'shipped' => ['method' => 'sendShippedNotification', 'label' => 'expédition'],
            'delivered' => ['method' => 'sendDeliveredNotification', 'label' => 'livraison'],
            'cancelled' => ['method' => 'sendCancelledNotification', 'label' => 'annulation'],
        ];

        $method = $labels[$type]['method'];
        $label = $labels[$type]['label'];

        try {
            $this->orderMailer->{$method}($order, $locale);
            $this->logger->info('{type} notification sent for order {reference}', [
                'type' => \ucfirst($type),
                'reference' => $order->getReference(),
            ]);
            $this->addFlash('success', \sprintf('Email de %s envoyé à %s', $label, $order->getCustomerEmail()));
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send {type} notification for order {reference}: {error}', [
                'type' => $type,
                'reference' => $order->getReference(),
                'error' => $e->getMessage(),
            ]);
            $this->addFlash('danger', \sprintf('Erreur lors de l\'envoi de l\'email de %s.', $label));
        }
    }

    private function sendAdminNotification(Order $order): void
    {
        try {
            $recipients = $this->orderMailer->sendNewOrderAdminNotification($order);

            if ($recipients !== []) {
                $emails = \array_map(static fn ($admin) => $admin->getEmail(), $recipients);
                $this->addFlash('success', \sprintf(
                    'Notification admin envoyée à %s',
                    \implode(', ', $emails),
                ));
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send admin notification for order {reference}: {error}', [
                'reference' => $order->getReference(),
                'error' => $e->getMessage(),
            ]);
            $this->addFlash('danger', 'Erreur lors de l\'envoi de la notification admin.');
        }
    }

    private function addFlash(string $type, string $message): void
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session = $this->requestStack->getSession();
        $session->getFlashBag()->add($type, $message);
    }
}
