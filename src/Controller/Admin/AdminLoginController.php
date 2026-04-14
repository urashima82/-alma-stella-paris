<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Admin;
use App\Repository\AdminRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

class AdminLoginController extends AbstractController
{
    public function __construct(
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {
    }

    #[Route('/admin/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        AdminRepository $adminRepository,
        LoginLinkHandlerInterface $loginLinkHandler,
        MailerInterface $mailer,
        RateLimiterFactory $adminLoginLimiter,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('admin');
        }

        $emailSent = false;

        if ($request->isMethod('POST')) {
            $limiter = $adminLoginLimiter->create($request->getClientIp() ?? 'unknown');
            if (!$limiter->consume()->isAccepted()) {
                return $this->render('admin/login.html.twig', [
                    'email_sent' => false,
                    'rate_limited' => true,
                ]);
            }

            $email = \trim((string) $request->request->get('email', ''));

            $admin = $adminRepository->findOneBy(['email' => $email]);

            if ($admin instanceof Admin) {
                $loginLinkDetails = $loginLinkHandler->createLoginLink($admin);

                $message = (new TemplatedEmail())
                    ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
                    ->to(new Address($admin->getEmail()))
                    ->subject('Your login link — Alma Stella Paris')
                    ->htmlTemplate('email/admin_login_link.html.twig')
                    ->context([
                        'login_link_url' => $loginLinkDetails->getUrl(),
                        'expiration_minutes' => 10,
                    ]);

                $mailer->send($message);
            }

            $emailSent = true;
        }

        return $this->render('admin/login.html.twig', [
            'email_sent' => $emailSent,
        ]);
    }

    #[Route('/admin/login-check', name: 'admin_login_check')]
    public function loginCheck(): never
    {
        throw new \LogicException('This route is handled by the login_link authenticator.');
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): never
    {
        throw new \LogicException('This route is handled by the security firewall.');
    }
}
