<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Service\OrderMailer;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class OrderStatusSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly OrderMailer $orderMailer,
        private readonly StripeService $stripeService,
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
            $this->cancelPaymentIntent($entity);
            $this->sendNotification($entity, 'cancelled');
        }
    }

    /**
     * A cancelled order must not stay payable: its client_secret would still
     * accept a charge that no verification pass will ever pick up again.
     * The intent's REAL status is asked to Stripe — paidAt only proves a
     * completed confirmation flow, and a Pending order whose 3DS payment
     * succeeded minutes ago still has paidAt = null: cancelling it must warn
     * about the captured money, not silently strand it.
     */
    private function cancelPaymentIntent(Order $order): void
    {
        $paymentIntentId = $order->getStripePaymentIntentId();

        if ($paymentIntentId === null) {
            return;
        }

        try {
            $paymentIntent = $this->stripeService->retrievePaymentIntent($paymentIntentId);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve PaymentIntent for order {reference}: {error}', [
                'reference' => $order->getReference(),
                'error' => $e->getMessage(),
            ]);
            $this->addFlash('warning', 'Impossible de vérifier le paiement Stripe : contrôlez-le dans le dashboard Stripe.');

            return;
        }

        if ($paymentIntent->status === 'succeeded') {
            $this->addFlash('warning', 'Le paiement de cette commande a déjà été capturé par Stripe : effectuez le remboursement depuis le dashboard Stripe.');

            return;
        }

        if ($paymentIntent->status === 'canceled') {
            return;
        }

        try {
            $this->stripeService->cancelPaymentIntent($paymentIntentId);
            $this->logger->info('PaymentIntent cancelled at Stripe for order {reference}.', [
                'reference' => $order->getReference(),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to cancel PaymentIntent for order {reference}: {error}', [
                'reference' => $order->getReference(),
                'error' => $e->getMessage(),
            ]);
            $this->addFlash('warning', 'Le paiement Stripe n\'a pas pu être annulé automatiquement : vérifiez-le dans le dashboard Stripe.');
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
