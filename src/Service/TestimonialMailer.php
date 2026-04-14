<?php

declare(strict_types=1);

namespace App\Service;

use App\Controller\Admin\TestimonialCrudController;
use App\Entity\Order;
use App\Entity\Testimonial;
use App\Repository\AdminRepository;
use App\Repository\OrderRepository;
use App\Repository\TestimonialRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TestimonialMailer
{
    private const int DAYS_AFTER_PAYMENT = 14;
    private const int MAX_DAYS_WINDOW = 45;

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly TestimonialRepository $testimonialRepository,
        private readonly AdminRepository $adminRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {
    }

    public function sendPendingRequests(): void
    {
        $orders = $this->orderRepository->findEligibleForTestimonialRequest(
            self::DAYS_AFTER_PAYMENT,
            self::MAX_DAYS_WINDOW,
        );

        foreach ($orders as $order) {
            $email = \strtolower(\trim($order->getCustomerEmail()));

            if ($this->testimonialRepository->existsForEmail($email)) {
                continue;
            }

            $this->createAndSend($order);
        }

        $this->entityManager->flush();
    }

    private function createAndSend(Order $order): void
    {
        $testimonial = new Testimonial();
        $testimonial->setEmail($order->getCustomerEmail());
        $testimonial->setRelatedOrder($order);

        $this->entityManager->persist($testimonial);

        $locale = $order->getCustomerLocale() ?? 'en';

        $formUrl = $this->urlGenerator->generate('shop_testimonial_submit', [
            '_locale' => $locale,
            'token' => $testimonial->getToken(),
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $subject = $locale === 'fr'
            ? 'Votre avis compte pour nous !'
            : 'Your feedback matters to us!';

        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
                ->to(new Address($order->getCustomerEmail(), $order->getCustomerName()))
                ->subject($subject)
                ->htmlTemplate('email/testimonial_request.html.twig')
                ->context([
                    'order' => $order,
                    'locale' => $locale,
                    'formUrl' => $formUrl,
                ]);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send testimonial request email to {email}: {error}', [
                'email' => $order->getCustomerEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendAdminNotification(Testimonial $testimonial): void
    {
        $recipients = $this->adminRepository->findEmailRecipients();

        if ($recipients === []) {
            return;
        }

        $adminUrl = $this->adminUrlGenerator
            ->setController(TestimonialCrudController::class)
            ->setAction('edit')
            ->setEntityId($testimonial->getId())
            ->generateUrl();

        $from = new Address($this->mailerFromEmail, $this->mailerFromName);

        foreach ($recipients as $admin) {
            try {
                $email = (new TemplatedEmail())
                    ->from($from)
                    ->to(new Address($admin->getEmail()))
                    ->subject(\sprintf('Nouveau témoignage de %s à modérer', $testimonial->getDisplayName()))
                    ->htmlTemplate('email/admin_new_testimonial.html.twig')
                    ->context([
                        'testimonial' => $testimonial,
                        'adminUrl' => $adminUrl,
                    ]);

                $this->mailer->send($email);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to send testimonial admin notification to {email}: {error}', [
                    'email' => $admin->getEmail(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
