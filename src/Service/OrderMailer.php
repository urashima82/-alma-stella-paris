<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Admin;
use App\Entity\Order;
use App\Repository\AdminRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OrderMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly AdminRepository $adminRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
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

        $invoiceUrl = $this->urlGenerator->generate('shop_invoice_download', [
            '_locale' => $locale,
            'reference' => $order->getReference(),
            'token' => $order->getInvoiceToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->to(new Address($order->getCustomerEmail(), $order->getCustomerName()))
            ->subject($subject)
            ->htmlTemplate('email/order_delivered.html.twig')
            ->context([
                'order' => $order,
                'locale' => $locale,
                'invoiceUrl' => $invoiceUrl,
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

    public function sendCancelledNotification(Order $order, string $locale = 'en'): void
    {
        $subject = 'fr' === $locale
            ? \sprintf('Votre commande %s a été annulée', $order->getReference())
            : \sprintf('Your order %s has been cancelled', $order->getReference());

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->to(new Address($order->getCustomerEmail(), $order->getCustomerName()))
            ->subject($subject)
            ->htmlTemplate('email/order_cancelled.html.twig')
            ->context([
                'order' => $order,
                'locale' => $locale,
            ]);

        $this->mailer->send($email);
    }

    /**
     * @return list<Admin>
     */
    public function sendNewOrderAdminNotification(Order $order): array
    {
        $recipients = $this->adminRepository->findEmailRecipients();

        if ([] === $recipients) {
            return [];
        }

        $adminUrl = $this->adminUrlGenerator
            ->setController(\App\Controller\Admin\OrderCrudController::class)
            ->setAction('edit')
            ->setEntityId($order->getId())
            ->generateUrl();

        $from = new Address($this->mailerFromEmail, $this->mailerFromName);

        foreach ($recipients as $admin) {
            $email = (new TemplatedEmail())
                ->from($from)
                ->to(new Address($admin->getEmail()))
                ->subject(\sprintf('Nouvelle commande %s en préparation', $order->getReference()))
                ->htmlTemplate('email/admin_new_order.html.twig')
                ->context([
                    'order' => $order,
                    'adminUrl' => $adminUrl,
                ]);

            $this->mailer->send($email);
        }

        return $recipients;
    }
}
