<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        CustomerRepository $customerRepository,
        OrderRepository $orderRepository,
        Security $security,
    ): Response {
        if ($this->getUser() instanceof Customer) {
            return $this->redirectToRoute('shop_account');
        }

        $errors = [];
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
                $customer = new Customer();
                $customer->setFirstName($formData['first_name']);
                $customer->setLastName($formData['last_name']);
                $customer->setEmail($formData['email']);
                $customer->setPassword($passwordHasher->hashPassword($customer, $password));

                $entityManager->persist($customer);

                // Link existing guest orders by email
                $guestOrders = $orderRepository->findBy(['customerEmail' => $customer->getEmail(), 'customer' => null]);
                foreach ($guestOrders as $guestOrder) {
                    $guestOrder->setCustomer($customer);
                }

                $entityManager->flush();

                $security->login($customer, 'form_login', 'main');

                return $this->redirectToRoute('shop_account');
            }
        }

        return $this->render('shop/security/register.html.twig', [
            'errors' => $errors,
            'form_data' => $formData,
        ]);
    }

    #[Route(
        path: ['en' => '/logout', 'fr' => '/deconnexion'],
        name: 'shop_logout',
    )]
    public function logout(): never
    {
        throw new \LogicException('This route is handled by the security firewall.');
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
