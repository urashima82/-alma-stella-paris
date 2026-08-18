<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\OrderConfirmationOutcome;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * The single confirmation path for a paid order, shared by the payment
 * confirmation endpoint and the pending-order verification scheduler.
 *
 * Everything that decides the order's fate happens in ONE transaction under
 * pessimistic locks (order row + product rows): the idempotence check, the
 * unique-piece conflict detection and the invoice number assignment. Unlocked
 * check-then-act variants of this flow allowed double confirmation (burned
 * invoice numbers, duplicate emails, even a wrongful full refund) and double
 * sale under concurrency — do not reintroduce them.
 *
 * When a conflict is found, its resolution is persisted BEFORE the Stripe
 * refund call, and the refund carries an idempotency key: a crash between the
 * two can then never cause a second refund, only a `refund_pending` order the
 * admin alert points at.
 */
final class OrderConfirmer
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly StripeService $stripeService,
        private readonly OrderMailer $orderMailer,
        private readonly ReservationManager $reservationManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function confirm(Order $order, string $stripePaymentStatus, string $locale): OrderConfirmationOutcome
    {
        // Process-level lock: serializes confirmations so invoice-number
        // generation stays collision-free even on the year's first invoice,
        // where the DB gap locks of two transactions do not conflict.
        $lock = $this->lockFactory->createLock('order-confirmation', ttl: 30.0);
        $lock->acquire(blocking: true);

        /** @var array{fullConflict: bool, refundAmountEur: float, refundedItemNames: list<string>}|null $conflict */
        $conflict = null;

        try {
            $outcome = $this->entityManager->wrapInTransaction(
                function () use ($order, $stripePaymentStatus, $locale, &$conflict): OrderConfirmationOutcome {
                    // Locked re-read: the authoritative status check.
                    $this->entityManager->refresh($order, LockMode::PESSIMISTIC_WRITE);

                    if ($order->getStatus() === OrderStatus::Cancelled) {
                        return OrderConfirmationOutcome::AlreadyCancelled;
                    }

                    if ($order->getStatus() !== OrderStatus::Pending) {
                        return OrderConfirmationOutcome::AlreadyProcessed;
                    }

                    $order->setStripePaymentStatus($stripePaymentStatus);

                    // Lock the product rows, then detect unique-piece conflicts
                    // on the fresh data.
                    $conflictItems = [];
                    foreach ($order->getItems() as $item) {
                        $product = $item->getProduct();
                        if ($product !== null) {
                            $this->entityManager->refresh($product, LockMode::PESSIMISTIC_WRITE);
                        }
                        if ($product === null || $product->isSoldOut()) {
                            $conflictItems[] = $item;
                        }
                    }

                    if ($conflictItems === []) {
                        $this->markConfirmed($order);

                        return OrderConfirmationOutcome::Confirmed;
                    }

                    $conflict = $this->claimConflict($order, $conflictItems, $locale);

                    if ($conflict['fullConflict']) {
                        $order->setStatus(OrderStatus::Cancelled);

                        return OrderConfirmationOutcome::CancelledFullConflict;
                    }

                    $this->markConfirmed($order);

                    return OrderConfirmationOutcome::ConfirmedPartialRefund;
                },
            );
        } finally {
            $lock->release();
        }

        // Post-commit side effects: the order's fate is already durable.
        if ($conflict !== null) {
            $this->executeRefund($order, $conflict, $locale);
        }

        if ($outcome === OrderConfirmationOutcome::Confirmed || $outcome === OrderConfirmationOutcome::ConfirmedPartialRefund) {
            $this->sendConfirmationEmails($order, $locale);
            $this->releaseReservations($order);
        }

        return $outcome;
    }

    /**
     * Status, paidAt, sold flags and the invoice number (locked read) — must
     * run inside the confirmation transaction.
     */
    private function markConfirmed(Order $order): void
    {
        $order->setStatus(OrderStatus::Processing);
        $order->setPaidAt(new \DateTimeImmutable());
        $order->setInvoiceNumber($this->orderRepository->nextInvoiceNumber((int) \date('Y'), lock: true));

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if ($product !== null && !$product->isSoldOut()) {
                $product->setIsSoldOut(true);
            }
        }
    }

    /**
     * Persist the conflict resolution on the order (inside the transaction,
     * before any Stripe call). The refund amount is the conflicting items'
     * share of what the customer actually paid: line totals carry per-item
     * discounts only, so the order-level coupon discount is applied
     * proportionally — refunding raw line totals over-refunds every couponed
     * order.
     *
     * @param OrderItem[] $conflictItems
     *
     * @return array{fullConflict: bool, refundAmountEur: float, refundedItemNames: list<string>}
     */
    private function claimConflict(Order $order, array $conflictItems, string $locale): array
    {
        $fullConflict = \count($conflictItems) === $order->getItems()->count();
        $surchargeApplies = $order->getShippingSurchargeEur() > 0.0;
        $paidTotal = $order->getTotalEur();
        $grossTotal = $paidTotal + $order->getDiscountAmountEur();

        $conflictGross = 0.0;
        $conflictShipping = 0.0;
        $refundedItemNames = [];

        foreach ($conflictItems as $item) {
            $conflictGross += $item->getLineTotal() + ($surchargeApplies ? $item->getShippingCost() : 0.0);
            $conflictShipping += $item->getShippingCost();
            $refundedItemNames[] = $item->getLocalizedProductName($locale);
        }

        $paidRatio = $grossTotal > 0.0 ? $paidTotal / $grossTotal : 0.0;
        $refundAmountEur = $fullConflict
            ? $paidTotal
            : \min(\round($conflictGross * $paidRatio, 2), $paidTotal);

        $order->setInternalNotes(\trim(\sprintf(
            "%s\n[%s] Conflit pièce unique : %s — remboursement de %.2f € à émettre.",
            $order->getInternalNotes() ?? '',
            (new \DateTimeImmutable())->format('Y-m-d H:i'),
            \implode(', ', $refundedItemNames),
            $refundAmountEur,
        )));
        $order->setStripePaymentStatus(Order::PAYMENT_STATUS_REFUND_PENDING);

        if (!$fullConflict) {
            foreach ($conflictItems as $item) {
                $order->removeItem($item);
            }

            // Keep the remaining totals coherent: total drops by the refund,
            // the discount sheds the share attributed to the removed items.
            $order->setTotalEur($paidTotal - $refundAmountEur);
            $order->setDiscountAmountEur(\max(0.0, $order->getDiscountAmountEur() - ($conflictGross - $refundAmountEur)));

            if ($surchargeApplies) {
                $order->setShippingSurchargeEur(\max(0.0, $order->getShippingSurchargeEur() - $conflictShipping));
            }
        }

        return [
            'fullConflict' => $fullConflict,
            'refundAmountEur' => $refundAmountEur,
            'refundedItemNames' => $refundedItemNames,
        ];
    }

    /**
     * Stripe refund + result bookkeeping + conflict emails. Runs after the
     * commit: the order is no longer Pending, so no flow can re-enter and
     * refund twice; the idempotency key additionally makes a Stripe-level
     * retry of this exact refund a no-op.
     *
     * @param array{fullConflict: bool, refundAmountEur: float, refundedItemNames: list<string>} $conflict
     */
    private function executeRefund(Order $order, array $conflict, string $locale): void
    {
        $stripeError = null;

        try {
            $this->stripeService->refundPaymentIntent(
                (string) $order->getStripePaymentIntentId(),
                $conflict['fullConflict'] ? null : $conflict['refundAmountEur'],
                idempotencyKey: 'conflict-refund-'.$order->getReference(),
            );
        } catch (\Exception $e) {
            $stripeError = $e->getMessage();
            $this->logger->critical('Automatic refund failed for order {ref}: {message}', [
                'ref' => $order->getReference(),
                'message' => $stripeError,
            ]);
        }

        if ($stripeError === null) {
            $order->setStripePaymentStatus(
                $conflict['fullConflict'] ? Order::PAYMENT_STATUS_REFUNDED : Order::PAYMENT_STATUS_PARTIALLY_REFUNDED,
            );
        } else {
            $order->setStripePaymentStatus(Order::PAYMENT_STATUS_REFUND_FAILED);
            $order->setInternalNotes(\trim(\sprintf(
                "%s\n[%s] ÉCHEC du remboursement automatique — À FAIRE MANUELLEMENT DANS STRIPE (%s).",
                $order->getInternalNotes() ?? '',
                (new \DateTimeImmutable())->format('Y-m-d H:i'),
                $stripeError,
            )));
        }

        $this->entityManager->flush();

        if ($stripeError === null) {
            try {
                $this->orderMailer->sendConflictRefundNotification(
                    $order,
                    $conflict['refundedItemNames'],
                    $conflict['refundAmountEur'],
                    $conflict['fullConflict'],
                    $locale,
                );
            } catch (\Exception $e) {
                $this->logger->error('Conflict refund email failed: {message}', ['message' => $e->getMessage()]);
            }
        }

        try {
            $this->orderMailer->sendConflictAdminAlert(
                $order,
                $conflict['refundedItemNames'],
                $conflict['refundAmountEur'],
                $conflict['fullConflict'],
                $stripeError,
            );
        } catch (\Exception $e) {
            $this->logger->error('Conflict admin alert failed: {message}', ['message' => $e->getMessage()]);
        }
    }

    private function sendConfirmationEmails(Order $order, string $locale): void
    {
        try {
            $this->orderMailer->sendOrderConfirmation($order, $locale);
        } catch (\Exception $e) {
            $this->logger->error('Order confirmation email failed: {message}', ['message' => $e->getMessage()]);
        }

        try {
            $this->orderMailer->sendNewOrderAdminNotification($order);
        } catch (\Exception $e) {
            $this->logger->error('Admin notification email failed: {message}', ['message' => $e->getMessage()]);
        }
    }

    private function releaseReservations(Order $order): void
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if ($product !== null) {
                $this->reservationManager->release($product);
            }
        }
    }
}
