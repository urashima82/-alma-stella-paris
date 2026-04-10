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
            if (null === $entity->getTrackingNumber() || '' === \trim($entity->getTrackingNumber())) {
                $this->addFlash('warning', 'Impossible de passer en « Expédiée » sans numéro de suivi.');
                $entity->setStatus($oldStatus);

                return;
            }

            $this->sendNotification($entity, 'shipped');
        }

        if (OrderStatus::Delivered === $newStatus) {
            $this->sendNotification($entity, 'delivered');
        }
    }

    private function sendNotification(Order $order, string $type): void
    {
        $locale = $order->getCustomerLocale() ?? 'en';
        $labels = [
            'shipped' => ['method' => 'sendShippedNotification', 'label' => 'expédition'],
            'delivered' => ['method' => 'sendDeliveredNotification', 'label' => 'livraison'],
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

    private function addFlash(string $type, string $message): void
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session = $this->requestStack->getSession();
        $session->getFlashBag()->add($type, $message);
    }
}
