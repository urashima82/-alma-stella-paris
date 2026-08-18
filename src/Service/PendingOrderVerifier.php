<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\OrderConfirmationOutcome;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class PendingOrderVerifier
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly StripeService $stripeService,
        private readonly OrderConfirmer $orderConfirmer,
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

            if ($paymentIntent->status === 'succeeded') {
                // Same confirmation path as the payment endpoint: idempotence,
                // unique-piece conflict refunds, invoice numbering and emails
                // (in the customer's locale) all live in OrderConfirmer.
                $outcome = $this->orderConfirmer->confirm($order, $paymentIntent->status, $order->getCustomerLocale() ?? 'en');

                if ($outcome === OrderConfirmationOutcome::CancelledFullConflict) {
                    $this->logger->warning('Order {ref} refunded and cancelled via scheduler (unique-piece conflict).', [
                        'ref' => $order->getReference(),
                    ]);
                    ++$result['cancelled'];
                } elseif ($outcome === OrderConfirmationOutcome::Confirmed || $outcome === OrderConfirmationOutcome::ConfirmedPartialRefund) {
                    $this->logger->info('Order {ref} confirmed via scheduler.', ['ref' => $order->getReference()]);
                    ++$result['confirmed'];
                } else {
                    ++$result['skipped'];
                }
            } elseif (\in_array($paymentIntent->status, ['canceled', 'requires_payment_method'], true)) {
                $age = $order->getCreatedAt()->diff(new \DateTimeImmutable());
                if ($age->h >= 1 || $age->days > 0) {
                    // Cancel the PaymentIntent at Stripe first: a still-open
                    // client_secret would let the customer pay a cancelled
                    // order that no verification pass will ever look at again.
                    if ($paymentIntent->status !== 'canceled') {
                        try {
                            $this->stripeService->cancelPaymentIntent((string) $order->getStripePaymentIntentId());
                        } catch (\Exception $e) {
                            // Leave the order Pending: the next run re-evaluates
                            // it (the intent may just have succeeded).
                            $this->logger->error('Failed to cancel PaymentIntent for order {ref}: {message}', [
                                'ref' => $order->getReference(),
                                'message' => $e->getMessage(),
                            ]);
                            ++$result['skipped'];

                            continue;
                        }
                    }

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
