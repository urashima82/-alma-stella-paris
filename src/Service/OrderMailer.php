<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class OrderMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {
    }

    public function sendOrderConfirmation(Order $order, string $locale = 'en'): void
    {
        $subject = 'fr' === $locale
            ? \sprintf('Confirmation de commande %s', $order->getReference())
            : \sprintf('Order confirmation %s', $order->getReference());

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->to(new Address($order->getCustomerEmail(), $order->getCustomerName()))
            ->subject($subject)
            ->htmlTemplate('email/order_confirmation.html.twig')
            ->context([
                'order' => $order,
                'locale' => $locale,
            ]);

        $this->mailer->send($email);
    }

    public function sendDeliveredNotification(Order $order, string $locale = 'en'): void
    {
        $subject = 'fr' === $locale
            ? \sprintf('Votre commande %s a été livrée', $order->getReference())
            : \sprintf('Your order %s has been delivered', $order->getReference());

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->to(new Address($order->getCustomerEmail(), $order->getCustomerName()))
            ->subject($subject)
            ->htmlTemplate('email/order_delivered.html.twig')
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
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
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
