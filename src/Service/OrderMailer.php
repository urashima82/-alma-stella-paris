<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class OrderMailer
{
    private const SENDER_EMAIL = 'hello@almastellaparis.com';
    private const SENDER_NAME = 'Alma Stella Paris';

    public function __construct(
        private readonly MailerInterface $mailer,
    ) {
    }

    public function sendOrderConfirmation(Order $order, string $locale = 'en'): void
    {
        $subject = 'fr' === $locale
            ? \sprintf('Confirmation de commande %s', $order->getReference())
            : \sprintf('Order confirmation %s', $order->getReference());

        $email = (new TemplatedEmail())
            ->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
            ->to(new Address($order->getCustomerEmail(), $order->getCustomerName()))
            ->subject($subject)
            ->htmlTemplate('email/order_confirmation.html.twig')
            ->context([
                'order' => $order,
                'locale' => $locale,
            ]);

        $this->mailer->send($email);
    }

    public function sendShippedNotification(Order $order, string $locale = 'en'): void
    {
        $subject = 'fr' === $locale
            ? \sprintf('Votre commande %s a été expédiée', $order->getReference())
            : \sprintf('Your order %s has been shipped', $order->getReference());

        $email = (new TemplatedEmail())
            ->from(new Address(self::SENDER_EMAIL, self::SENDER_NAME))
            ->to(new Address($order->getCustomerEmail(), $order->getCustomerName()))
            ->subject($subject)
            ->htmlTemplate('email/order_shipped.html.twig')
            ->context([
                'order' => $order,
                'locale' => $locale,
            ]);

        $this->mailer->send($email);
    }
}
