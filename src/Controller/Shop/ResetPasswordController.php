<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {
    }

    #[Route(
        path: ['en' => '/forgot-password', 'fr' => '/mot-de-passe-oublie'],
        name: 'shop_forgot_password',
        methods: ['GET', 'POST'],
    )]
    public function request(
        Request $request,
        CustomerRepository $customerRepository,
        MailerInterface $mailer,
    ): Response {
        if ($this->getUser() instanceof Customer) {
            return $this->redirectToRoute('shop_account');
        }

        $emailSent = false;

        if ($request->isMethod('POST')) {
            $email = \strtolower(\trim((string) $request->request->get('email', '')));

            $customer = $customerRepository->findByEmail($email);

            if ($customer instanceof Customer) {
                try {
                    $resetToken = $this->resetPasswordHelper->generateResetToken($customer);

                    $message = (new TemplatedEmail())
                        ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
                        ->to(new Address($customer->getEmail(), $customer->getFullName()))
                        ->subject($request->getLocale() === 'en'
                            ? 'Reset your password — Alma Stella Paris'
                            : 'Réinitialisation de votre mot de passe — Alma Stella Paris')
                        ->htmlTemplate('email/reset_password.html.twig')
                        ->context([
                            'resetToken' => $resetToken,
                            'customer' => $customer,
                            'locale' => $request->getLocale(),
                        ]);

                    $mailer->send($message);
                } catch (ResetPasswordExceptionInterface) {
                    // Silently ignore — don't reveal whether the email exists
                }
            }

            $emailSent = true;
        }

        return $this->render('shop/security/forgot_password.html.twig', [
            'email_sent' => $emailSent,
        ]);
    }

    #[Route(
        path: ['en' => '/reset-password/{token}', 'fr' => '/reinitialiser-mot-de-passe/{token}'],
        name: 'shop_reset_password',
        methods: ['GET', 'POST'],
    )]
    public function reset(
        Request $request,
        string $token,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $errors = [];

        try {
            /** @var Customer $customer */
            $customer = $this->resetPasswordHelper->validateTokenAndFetchUser($token);
        } catch (ResetPasswordExceptionInterface) {
            return $this->render('shop/security/reset_password.html.twig', [
                'errors' => ['reset_password.error.token_expired'],
                'token_invalid' => true,
            ]);
        }

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            if (\strlen($password) < 8) {
                $errors[] = 'reset_password.error.password_too_short';
            }

            if ($password !== $passwordConfirm) {
                $errors[] = 'reset_password.error.password_mismatch';
            }

            if ($errors === []) {
                $this->resetPasswordHelper->removeResetRequest($token);

                $customer->setPassword($passwordHasher->hashPassword($customer, $password));
                $this->entityManager->flush();

                return $this->redirectToRoute('shop_login', [
                    '_locale' => $request->getLocale(),
                    'reset' => 1,
                ]);
            }
        }

        return $this->render('shop/security/reset_password.html.twig', [
            'errors' => $errors,
            'token_invalid' => false,
            'token' => $token,
        ]);
    }
}
