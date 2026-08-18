<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\StripeClient;

final class StripeService
{
    /** Stripe rejects any EUR PaymentIntent below this amount. */
    public const float MINIMUM_CHARGE_EUR = 0.50;

    private StripeClient $stripe;

    public function __construct(
        private readonly string $stripeSecretKey,
    ) {
        $this->stripe = new StripeClient($this->stripeSecretKey);
    }

    public function createPaymentIntent(Order $order): PaymentIntent
    {
        $amountInCents = (int) \round($order->getTotalEur() * 100);

        return $this->stripe->paymentIntents->create([
            'amount' => $amountInCents,
            'currency' => 'eur',
            'metadata' => [
                'order_reference' => $order->getReference(),
            ],
            'automatic_payment_methods' => [
                'enabled' => true,
            ],
        ]);
    }

    public function retrievePaymentIntent(string $paymentIntentId): PaymentIntent
    {
        return $this->stripe->paymentIntents->retrieve($paymentIntentId);
    }

    /**
     * Cancel a PaymentIntent so its client_secret can no longer be charged.
     * Stripe only allows cancellation while the intent is not succeeded.
     */
    public function cancelPaymentIntent(string $paymentIntentId): void
    {
        $this->stripe->paymentIntents->cancel($paymentIntentId);
    }

    /**
     * Refund a succeeded PaymentIntent, fully (null amount) or partially.
     * Pass an idempotency key so a retried call cannot refund twice.
     */
    public function refundPaymentIntent(string $paymentIntentId, ?float $amountEur = null, ?string $idempotencyKey = null): Refund
    {
        $params = ['payment_intent' => $paymentIntentId];

        if ($amountEur !== null) {
            $params['amount'] = (int) \round($amountEur * 100);
        }

        return $this->stripe->refunds->create(
            $params,
            $idempotencyKey !== null ? ['idempotency_key' => $idempotencyKey] : null,
        );
    }
}
