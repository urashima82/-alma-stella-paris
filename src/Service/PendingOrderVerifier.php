<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class PendingOrderVerifier
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly StripeService $stripeService,
        private readonly OrderMailer $orderMailer,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{confirmed: int, cancelled: int, skipped: int}
     */
    public function verifyAll(): array
    {
        $result = ['confirmed' => 0, 'cancelled' => 0, 'skipped' => 0];

        $pendingOrders = $this->orderRepository->findPendingWithPaymentIntent();

        foreach ($pendingOrders as $order) {
            try {
                $paymentIntent = $this->stripeService->retrievePaymentIntent(
                    (string) $order->getStripePaymentIntentId()
                );
            } catch (\Exception $e) {
                $this->logger->error('Failed to retrieve PaymentIntent for order {ref}: {message}', [
                    'ref' => $order->getReference(),
                    'message' => $e->getMessage(),
                ]);
                ++$result['skipped'];

                continue;
            }

            $order->setStripePaymentStatus($paymentIntent->status);

            if ('succeeded' === $paymentIntent->status) {
                $order->setStatus(OrderStatus::Processing);

                foreach ($order->getItems() as $item) {
                    $product = $item->getProduct();
                    if (null !== $product && !$product->isSoldOut()) {
                        $product->setIsSoldOut(true);
                    }
                }

                $this->entityManager->flush();

                try {
                    $this->orderMailer->sendOrderConfirmation($order);
                } catch (\Exception $e) {
                    $this->logger->error('Confirmation email failed for order {ref}: {message}', [
                        'ref' => $order->getReference(),
                        'message' => $e->getMessage(),
                    ]);
                }

                $this->logger->info('Order {ref} confirmed via scheduler.', ['ref' => $order->getReference()]);
                ++$result['confirmed'];
            } elseif (\in_array($paymentIntent->status, ['canceled', 'requires_payment_method'], true)) {
                $age = $order->getCreatedAt()->diff(new \DateTimeImmutable());
                if ($age->h >= 1 || $age->days > 0) {
                    $order->setStatus(OrderStatus::Cancelled);
                    $this->entityManager->flush();

                    $this->logger->info('Order {ref} cancelled (payment {status}).', [
                        'ref' => $order->getReference(),
                        'status' => $paymentIntent->status,
                    ]);
                    ++$result['cancelled'];
                } else {
                    ++$result['skipped'];
                }
            } else {
                ++$result['skipped'];
            }
        }

        return $result;
    }
}
