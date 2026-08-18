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
        $subject = $locale === 'fr'
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
        $subject = $locale === 'fr'
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
        $subject = $locale === 'fr'
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
        $subject = $locale === 'fr'
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

    /** @param list<string> $refundedItemNames */
    public function sendConflictRefundNotification(Order $order, array $refundedItemNames, float $refundedAmountEur, bool $orderCancelled, string $locale = 'en'): void
    {
        if ($orderCancelled) {
            $subject = $locale === 'fr'
                ? \sprintf('Commande %s annulée et remboursée', $order->getReference())
                : \sprintf('Order %s cancelled and refunded', $order->getReference());
        } else {
            $subject = $locale === 'fr'
                ? \sprintf('Remboursement partiel — commande %s', $order->getReference())
                : \sprintf('Partial refund — order %s', $order->getReference());
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
            ->to(new Address($order->getCustomerEmail(), $order->getCustomerName()))
            ->subject($subject)
            ->htmlTemplate('email/order_conflict_refund.html.twig')
            ->context([
                'order' => $order,
                'locale' => $locale,
                'refundedItemNames' => $refundedItemNames,
                'refundedAmountEur' => $refundedAmountEur,
                'orderCancelled' => $orderCancelled,
            ]);

        $this->mailer->send($email);
    }

    /**
     * @param list<string> $refundedItemNames
     *
     * @return list<Admin>
     */
    public function sendConflictAdminAlert(Order $order, array $refundedItemNames, float $refundedAmountEur, bool $orderCancelled, ?string $stripeError = null): array
    {
        $recipients = $this->adminRepository->findEmailRecipients();

        if ($recipients === []) {
            return [];
        }

        $adminUrl = $this->adminUrlGenerator
            ->setController(\App\Controller\Admin\OrderCrudController::class)
            ->setAction('edit')
            ->setEntityId($order->getId())
            ->generateUrl();

        $subject = $stripeError !== null
            ? \sprintf('⚠ Conflit pièce unique — commande %s — remboursement manuel requis', $order->getReference())
            : \sprintf('⚠ Conflit pièce unique — commande %s', $order->getReference());

        $from = new Address($this->mailerFromEmail, $this->mailerFromName);

        foreach ($recipients as $admin) {
            $email = (new TemplatedEmail())
                ->from($from)
                ->to(new Address($admin->getEmail()))
                ->subject($subject)
                ->htmlTemplate('email/admin_conflict_alert.html.twig')
                ->context([
                    'order' => $order,
                    'adminUrl' => $adminUrl,
                    'refundedItemNames' => $refundedItemNames,
                    'refundedAmountEur' => $refundedAmountEur,
                    'orderCancelled' => $orderCancelled,
                    'stripeError' => $stripeError,
                ]);

            $this->mailer->send($email);
        }

        return $recipients;
    }

    /**
     * @return list<Admin>
     */
    public function sendNewOrderAdminNotification(Order $order): array
    {
        $recipients = $this->adminRepository->findEmailRecipients();

        if ($recipients === []) {
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
