<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ContactMessage;
use App\Repository\AdminRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContactMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly AdminRepository $adminRepository,
        private readonly TranslatorInterface $translator,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {
    }

    public function sendContactNotification(ContactMessage $contactMessage): void
    {
        $recipients = $this->adminRepository->findEmailRecipients();

        if ([] === $recipients) {
            return;
        }

        $subjectLabel = $this->translator->trans($contactMessage->getSubject()->label(), locale: 'fr');

        $body = \sprintf(
            "Nouveau message de contact\n"
            ."══════════════════════════\n\n"
            ."Nom : %s\n"
            ."Email : %s\n"
            ."Sujet : %s\n"
            ."Date : %s\n\n"
            ."Message :\n"
            ."──────────\n"
            ."%s\n\n"
            ."──────────\n"
            .'Répondre directement à cet e-mail pour contacter le client.',
            $contactMessage->getName(),
            $contactMessage->getEmail(),
            $subjectLabel,
            $contactMessage->getCreatedAt()->format('d/m/Y à H:i'),
            $contactMessage->getMessage(),
        );

        $from = new Address($this->mailerFromEmail, $this->mailerFromName);

        foreach ($recipients as $admin) {
            $email = (new Email())
                ->from($from)
                ->replyTo(new Address($contactMessage->getEmail(), $contactMessage->getName()))
                ->to(new Address($admin->getEmail()))
                ->subject(\sprintf('[Contact] %s — %s', $subjectLabel, $contactMessage->getName()))
                ->text($body);

            $this->mailer->send($email);
        }
    }
}
