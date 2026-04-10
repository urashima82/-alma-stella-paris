<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Stripe\PaymentIntent;
use Stripe\StripeClient;

final class StripeService
{
    private StripeClient $stripe;

    public function __construct(
        private readonly string $stripeSecretKey,
    ) {
        $this->stripe = new StripeClient($this->stripeSecretKey);
    }

    public function createPaymentIntent(Order $order): PaymentIntent
    {
        $amountInCents = (int) \round($order->getTotalUsd() * 100);

        return $this->stripe->paymentIntents->create([
            'amount' => $amountInCents,
            'currency' => 'usd',
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
}
