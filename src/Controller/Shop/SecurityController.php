<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(
        path: ['en' => '/login', 'fr' => '/connexion'],
        name: 'shop_login',
        methods: ['GET', 'POST'],
    )]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() instanceof Customer) {
            return $this->redirectToRoute('shop_account');
        }

        return $this->render('shop/security/login.html.twig', [
            'last_email' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route(
        path: ['en' => '/register', 'fr' => '/inscription'],
        name: 'shop_register',
        methods: ['GET', 'POST'],
    )]
    public function register(
        Request $request,
        CustomerRepository $customerRepository,
        MailerInterface $mailer,
        string $mailerFromEmail,
        string $mailerFromName,
    ): Response {
        if ($this->getUser() instanceof Customer) {
            return $this->redirectToRoute('shop_account');
        }

        $errors = [];
        $redirect = $request->query->get('redirect', $request->request->get('redirect', ''));
        $formData = [
            'first_name' => '',
            'last_name' => '',
            'email' => $request->query->get('email', ''),
        ];

        if ($request->isMethod('POST')) {
            $formData['first_name'] = \trim((string) $request->request->get('first_name', ''));
            $formData['last_name'] = \trim((string) $request->request->get('last_name', ''));
            $formData['email'] = \trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');
            $passwordConfirm = (string) $request->request->get('password_confirm', '');

            $errors = $this->validateRegistration($formData, $password, $passwordConfirm, $customerRepository);

            if ([] === $errors) {
                $code = \str_pad((string) \random_int(0, 999999), 6, '0', \STR_PAD_LEFT);
                $locale = $request->getLocale();

                $session = $request->getSession();
                $session->set('_registration_data', [
                    'first_name' => $formData['first_name'],
                    'last_name' => $formData['last_name'],
                    'email' => $formData['email'],
                    'password' => $password,
                ]);
                $session->set('_registration_otp', $code);
                $session->set('_registration_otp_expires', \time() + 600);
                $session->set('_registration_otp_attempts', 0);

                $subject = 'fr' === $locale
                    ? 'Votre code de vérification — Alma Stella Paris'
                    : 'Your verification code — Alma Stella Paris';

                $email = (new TemplatedEmail())
                    ->from(new Address($mailerFromEmail, $mailerFromName))
                    ->to(new Address($formData['email'], $formData['first_name']))
                    ->subject($subject)
                    ->htmlTemplate('email/registration_otp.html.twig')
                    ->context([
                        'firstName' => $formData['first_name'],
                        'code' => $code,
                        'locale' => $locale,
                    ]);

                $mailer->send($email);

                $verifyParams = ['_locale' => $locale];
                if ('checkout' === $redirect) {
                    $verifyParams['redirect'] = 'checkout';
                }

                return $this->redirectToRoute('shop_verify_email', $verifyParams);
            }
        }

        return $this->render('shop/security/register.html.twig', [
            'errors' => $errors,
            'form_data' => $formData,
            'redirect' => $redirect,
        ]);
    }

    #[Route(
        path: ['en' => '/verify-email', 'fr' => '/verification-email'],
        name: 'shop_verify_email',
        methods: ['GET', 'POST'],
    )]
    public function verifyEmail(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        CustomerRepository $customerRepository,
        OrderRepository $orderRepository,
        Security $security,
    ): Response {
        if ($this->getUser() instanceof Customer) {
            return $this->redirectToRoute('shop_account');
        }

        $session = $request->getSession();
        $registrationData = $session->get('_registration_data');

        if (null === $registrationData) {
            return $this->redirectToRoute('shop_register');
        }

        $errors = [];
        $redirect = $request->query->get('redirect', $request->request->get('redirect', ''));

        if ($request->isMethod('POST')) {
            $submittedCode = \trim((string) $request->request->get('code', ''));
            $storedCode = (string) $session->get('_registration_otp', '');
            $expiresAt = (int) $session->get('_registration_otp_expires', 0);
            $attempts = (int) $session->get('_registration_otp_attempts', 0);

            if ($attempts >= 5) {
                $this->clearRegistrationSession($session);

                return $this->redirectToRoute('shop_register');
            }

            if (\time() > $expiresAt) {
                $errors[] = 'verify_email.error.code_expired';
            } elseif ($submittedCode !== $storedCode) {
                $session->set('_registration_otp_attempts', $attempts + 1);
                $errors[] = 'verify_email.error.code_invalid';
            } else {
                // Re-check email uniqueness (could have been taken during OTP delay)
                if (null !== $customerRepository->findByEmail($registrationData['email'])) {
                    $this->clearRegistrationSession($session);
                    $this->addFlash('error', 'register.error.email_already_used');

                    return $this->redirectToRoute('shop_register');
                }

                $customer = new Customer();
                $customer->setFirstName($registrationData['first_name']);
                $customer->setLastName($registrationData['last_name']);
                $customer->setEmail($registrationData['email']);
                $customer->setPassword($passwordHasher->hashPassword($customer, $registrationData['password']));

                $entityManager->persist($customer);

                // Link existing guest orders by email
                $guestOrders = $orderRepository->findBy(['customerEmail' => $customer->getEmail(), 'customer' => null]);
                foreach ($guestOrders as $guestOrder) {
                    $guestOrder->setCustomer($customer);
                }

                $entityManager->flush();

                $this->clearRegistrationSession($session);

                $security->login($customer, 'form_login', 'main');

                if ('checkout' === $redirect) {
                    $checkoutRoute = 'fr' === $request->getLocale() ? 'shop_checkout_fr' : 'shop_checkout';

                    return $this->redirectToRoute($checkoutRoute, ['_locale' => $request->getLocale()]);
                }

                return $this->redirectToRoute('shop_account');
            }
        }

        return $this->render('shop/security/verify_email.html.twig', [
            'email' => $registrationData['email'],
            'errors' => $errors,
            'redirect' => $redirect,
        ]);
    }

    #[Route(
        path: ['en' => '/verify-email/resend', 'fr' => '/verification-email/renvoyer'],
        name: 'shop_verify_email_resend',
        methods: ['GET'],
    )]
    public function resendOtp(
        Request $request,
        MailerInterface $mailer,
        string $mailerFromEmail,
        string $mailerFromName,
    ): Response {
        if ($this->getUser() instanceof Customer) {
            return $this->redirectToRoute('shop_account');
        }

        $session = $request->getSession();
        $registrationData = $session->get('_registration_data');

        if (null === $registrationData) {
            return $this->redirectToRoute('shop_register');
        }

        $code = \str_pad((string) \random_int(0, 999999), 6, '0', \STR_PAD_LEFT);
        $locale = $request->getLocale();

        $session->set('_registration_otp', $code);
        $session->set('_registration_otp_expires', \time() + 600);
        $session->set('_registration_otp_attempts', 0);

        $subject = 'fr' === $locale
            ? 'Votre code de vérification — Alma Stella Paris'
            : 'Your verification code — Alma Stella Paris';

        $email = (new TemplatedEmail())
            ->from(new Address($mailerFromEmail, $mailerFromName))
            ->to(new Address($registrationData['email'], $registrationData['first_name']))
            ->subject($subject)
            ->htmlTemplate('email/registration_otp.html.twig')
            ->context([
                'firstName' => $registrationData['first_name'],
                'code' => $code,
                'locale' => $locale,
            ]);

        $mailer->send($email);

        return $this->redirectToRoute('shop_verify_email');
    }

    #[Route(
        path: ['en' => '/logout', 'fr' => '/deconnexion'],
        name: 'shop_logout',
    )]
    public function logout(): never
    {
        throw new \LogicException('This route is handled by the security firewall.');
    }

    private function clearRegistrationSession(SessionInterface $session): void
    {
        $session->remove('_registration_data');
        $session->remove('_registration_otp');
        $session->remove('_registration_otp_expires');
        $session->remove('_registration_otp_attempts');
    }

    /**
     * @param array{first_name: string, last_name: string, email: string} $formData
     *
     * @return list<string>
     */
    private function validateRegistration(array $formData, string $password, string $passwordConfirm, CustomerRepository $customerRepository): array
    {
        $errors = [];

        if ('' === $formData['first_name']) {
            $errors[] = 'register.error.first_name_required';
        }

        if ('' === $formData['last_name']) {
            $errors[] = 'register.error.last_name_required';
        }

        if ('' === $formData['email'] || false === \filter_var($formData['email'], \FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'register.error.email_invalid';
        } elseif (null !== $customerRepository->findByEmail($formData['email'])) {
            $errors[] = 'register.error.email_already_used';
        }

        if (\strlen($password) < 8) {
            $errors[] = 'register.error.password_too_short';
        }

        if ($password !== $passwordConfirm) {
            $errors[] = 'register.error.password_mismatch';
        }

        return $errors;
    }
}
