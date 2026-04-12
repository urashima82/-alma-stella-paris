<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Repository\CustomerAddressRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CUSTOMER')]
class AccountController extends AbstractController
{
    #[Route(
        path: ['en' => '/account', 'fr' => '/mon-compte'],
        name: 'shop_account',
    )]
    public function dashboard(): Response
    {
        /** @var Customer $customer */
        $customer = $this->getUser();

        return $this->render('shop/account/dashboard.html.twig', [
            'customer' => $customer,
        ]);
    }

    #[Route(
        path: ['en' => '/account/orders', 'fr' => '/mon-compte/commandes'],
        name: 'shop_account_orders',
    )]
    public function orders(
        Request $request,
        OrderRepository $orderRepository,
        PaginatorInterface $paginator,
    ): Response {
        /** @var Customer $customer */
        $customer = $this->getUser();

        $query = $orderRepository->createQueryBuilder('o')
            ->where('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery();

        $pagination = $paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            10,
        );

        return $this->render('shop/account/orders.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route(
        path: ['en' => '/account/orders/{reference}', 'fr' => '/mon-compte/commandes/{reference}'],
        name: 'shop_account_order_detail',
    )]
    public function orderDetail(
        string $reference,
        OrderRepository $orderRepository,
    ): Response {
        /** @var Customer $customer */
        $customer = $this->getUser();

        $order = $orderRepository->findOneBy([
            'reference' => $reference,
            'customer' => $customer,
        ]);

        if (null === $order) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->render('shop/account/order_detail.html.twig', [
            'order' => $order,
        ]);
    }

    #[Route(
        path: ['en' => '/account/addresses', 'fr' => '/mon-compte/adresses'],
        name: 'shop_account_addresses',
    )]
    public function addresses(): Response
    {
        /** @var Customer $customer */
        $customer = $this->getUser();

        return $this->render('shop/account/addresses.html.twig', [
            'addresses' => $customer->getAddresses(),
        ]);
    }

    #[Route(
        path: ['en' => '/account/addresses/add', 'fr' => '/mon-compte/adresses/ajouter'],
        name: 'shop_account_address_add',
        methods: ['GET', 'POST'],
    )]
    public function addAddress(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var Customer $customer */
        $customer = $this->getUser();

        $errors = [];
        $formData = $this->getEmptyAddressFormData();

        if ($request->isMethod('POST')) {
            $formData = $this->extractAddressFormData($request);
            $errors = $this->validateAddressForm($formData);

            if ([] === $errors) {
                $address = $this->createAddressFromFormData($formData, $customer);

                if ($address->isDefault()) {
                    $this->clearDefaultAddresses($customer, $entityManager);
                }

                $entityManager->persist($address);
                $entityManager->flush();

                return $this->redirectToRoute('shop_account_addresses', ['_locale' => $request->getLocale()]);
            }
        }

        return $this->render('shop/account/address_form.html.twig', [
            'form_data' => $formData,
            'errors' => $errors,
            'is_edit' => false,
            'countries' => self::getShippingCountries($request->getLocale()),
        ]);
    }

    #[Route(
        path: ['en' => '/account/addresses/{id}/edit', 'fr' => '/mon-compte/adresses/{id}/modifier'],
        name: 'shop_account_address_edit',
        methods: ['GET', 'POST'],
    )]
    public function editAddress(
        int $id,
        Request $request,
        CustomerAddressRepository $addressRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var Customer $customer */
        $customer = $this->getUser();

        $address = $addressRepository->find($id);
        if (null === $address || $address->getCustomer() !== $customer) {
            throw $this->createNotFoundException('Address not found.');
        }

        $errors = [];
        $formData = [
            'label' => $address->getLabel(),
            'recipient_name' => $address->getRecipientName() ?? '',
            'address_line1' => $address->getAddressLine1(),
            'address_line2' => $address->getAddressLine2() ?? '',
            'city' => $address->getCity(),
            'state' => $address->getState() ?? '',
            'postal_code' => $address->getPostalCode(),
            'country' => $address->getCountry(),
            'is_default' => $address->isDefault(),
        ];

        if ($request->isMethod('POST')) {
            $formData = $this->extractAddressFormData($request);
            $errors = $this->validateAddressForm($formData);

            if ([] === $errors) {
                $this->updateAddressFromFormData($formData, $address);

                if ($address->isDefault()) {
                    $this->clearDefaultAddresses($customer, $entityManager, $address);
                }

                $entityManager->flush();

                return $this->redirectToRoute('shop_account_addresses', ['_locale' => $request->getLocale()]);
            }
        }

        return $this->render('shop/account/address_form.html.twig', [
            'form_data' => $formData,
            'errors' => $errors,
            'is_edit' => true,
            'address_id' => $id,
            'countries' => self::getShippingCountries($request->getLocale()),
        ]);
    }

    #[Route(
        path: ['en' => '/account/addresses/{id}/delete', 'fr' => '/mon-compte/adresses/{id}/supprimer'],
        name: 'shop_account_address_delete',
        methods: ['POST'],
    )]
    public function deleteAddress(
        int $id,
        CustomerAddressRepository $addressRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var Customer $customer */
        $customer = $this->getUser();

        $address = $addressRepository->find($id);
        if (null === $address || $address->getCustomer() !== $customer) {
            throw $this->createNotFoundException('Address not found.');
        }

        $entityManager->remove($address);
        $entityManager->flush();

        return $this->redirectToRoute('shop_account_addresses');
    }

    #[Route(
        path: ['en' => '/account/addresses/{id}/default', 'fr' => '/mon-compte/adresses/{id}/par-defaut'],
        name: 'shop_account_address_set_default',
        methods: ['POST'],
    )]
    public function setDefaultAddress(
        int $id,
        CustomerAddressRepository $addressRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var Customer $customer */
        $customer = $this->getUser();

        $address = $addressRepository->find($id);
        if (null === $address || $address->getCustomer() !== $customer) {
            throw $this->createNotFoundException('Address not found.');
        }

        $this->clearDefaultAddresses($customer, $entityManager);
        $address->setIsDefault(true);
        $entityManager->flush();

        return $this->redirectToRoute('shop_account_addresses');
    }

    #[Route(
        path: ['en' => '/account/profile', 'fr' => '/mon-compte/profil'],
        name: 'shop_account_profile',
        methods: ['GET', 'POST'],
    )]
    public function profile(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var Customer $customer */
        $customer = $this->getUser();
        $errors = [];
        $success = null;
        $action = $request->request->get('_action');

        if ($request->isMethod('POST') && 'update_info' === $action) {
            $firstName = \trim((string) $request->request->get('first_name', ''));
            $lastName = \trim((string) $request->request->get('last_name', ''));
            $email = \trim((string) $request->request->get('email', ''));

            if ('' !== $firstName && '' !== $lastName && '' !== $email) {
                $customer->setFirstName($firstName);
                $customer->setLastName($lastName);
                $customer->setEmail($email);
                $entityManager->flush();
                $success = 'profile.success.info_updated';
            }
        }

        if ($request->isMethod('POST') && 'change_password' === $action) {
            $currentPassword = (string) $request->request->get('current_password', '');
            $newPassword = (string) $request->request->get('new_password', '');
            $newPasswordConfirm = (string) $request->request->get('new_password_confirm', '');

            if (!$passwordHasher->isPasswordValid($customer, $currentPassword)) {
                $errors[] = 'profile.error.current_password_invalid';
            } elseif (\strlen($newPassword) < 8) {
                $errors[] = 'profile.error.password_too_short';
            } elseif ($newPassword !== $newPasswordConfirm) {
                $errors[] = 'profile.error.password_mismatch';
            } else {
                $customer->setPassword($passwordHasher->hashPassword($customer, $newPassword));
                $entityManager->flush();
                $success = 'profile.success.password_changed';
            }
        }

        return $this->render('shop/account/profile.html.twig', [
            'customer' => $customer,
            'errors' => $errors,
            'success' => $success,
        ]);
    }

    /**
     * @return array{label: string, recipient_name: string, address_line1: string, address_line2: string, city: string, state: string, postal_code: string, country: string, is_default: bool}
     */
    private function getEmptyAddressFormData(): array
    {
        return [
            'label' => '',
            'recipient_name' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => '',
            'is_default' => false,
        ];
    }

    /**
     * @return array{label: string, recipient_name: string, address_line1: string, address_line2: string, city: string, state: string, postal_code: string, country: string, is_default: bool}
     */
    private function extractAddressFormData(Request $request): array
    {
        return [
            'label' => \trim((string) $request->request->get('label', '')),
            'recipient_name' => \trim((string) $request->request->get('recipient_name', '')),
            'address_line1' => \trim((string) $request->request->get('address_line1', '')),
            'address_line2' => \trim((string) $request->request->get('address_line2', '')),
            'city' => \trim((string) $request->request->get('city', '')),
            'state' => \trim((string) $request->request->get('state', '')),
            'postal_code' => \trim((string) $request->request->get('postal_code', '')),
            'country' => \trim((string) $request->request->get('country', '')),
            'is_default' => (bool) $request->request->get('is_default', false),
        ];
    }

    /**
     * @param array{label: string, recipient_name: string, address_line1: string, address_line2: string, city: string, state: string, postal_code: string, country: string, is_default: bool} $formData
     *
     * @return list<string>
     */
    private function validateAddressForm(array $formData): array
    {
        $errors = [];

        if ('' === $formData['label']) {
            $errors[] = 'addresses.error.label_required';
        }

        if ('' === $formData['address_line1']) {
            $errors[] = 'addresses.error.address_required';
        }

        if ('' === $formData['city']) {
            $errors[] = 'addresses.error.city_required';
        }

        if ('' === $formData['postal_code']) {
            $errors[] = 'addresses.error.postal_code_required';
        }

        if ('' === $formData['country'] || 2 !== \strlen($formData['country'])) {
            $errors[] = 'addresses.error.country_required';
        }

        return $errors;
    }

    /**
     * @param array{label: string, recipient_name: string, address_line1: string, address_line2: string, city: string, state: string, postal_code: string, country: string, is_default: bool} $formData
     */
    private function createAddressFromFormData(array $formData, Customer $customer): CustomerAddress
    {
        $address = new CustomerAddress();
        $address->setCustomer($customer);
        $this->updateAddressFromFormData($formData, $address);

        return $address;
    }

    /**
     * @param array{label: string, recipient_name: string, address_line1: string, address_line2: string, city: string, state: string, postal_code: string, country: string, is_default: bool} $formData
     */
    private function updateAddressFromFormData(array $formData, CustomerAddress $address): void
    {
        $address->setLabel($formData['label']);
        $address->setRecipientName('' !== $formData['recipient_name'] ? $formData['recipient_name'] : null);
        $address->setAddressLine1($formData['address_line1']);
        $address->setAddressLine2('' !== $formData['address_line2'] ? $formData['address_line2'] : null);
        $address->setCity($formData['city']);
        $address->setState('' !== $formData['state'] ? $formData['state'] : null);
        $address->setPostalCode($formData['postal_code']);
        $address->setCountry($formData['country']);
        $address->setIsDefault($formData['is_default']);
    }

    private function clearDefaultAddresses(Customer $customer, EntityManagerInterface $entityManager, ?CustomerAddress $except = null): void
    {
        foreach ($customer->getAddresses() as $addr) {
            if ($addr !== $except && $addr->isDefault()) {
                $addr->setIsDefault(false);
            }
        }
    }

    private const SHIPPING_COUNTRY_CODES = [
        'US', 'CA', 'FR', 'GB', 'MX', 'DE', 'ES', 'IT', 'NL', 'BE',
        'CH', 'AT', 'PT', 'IE', 'AU', 'NZ', 'JP', 'KR', 'SG', 'AE',
        'BR', 'CO', 'CL', 'AR', 'SE', 'DK', 'NO', 'FI', 'PL', 'CZ',
        'GR', 'IL', 'TH', 'MY', 'PH', 'IN',
    ];

    /**
     * @return array<string, string>
     */
    private static function getShippingCountries(string $locale): array
    {
        $countries = [];
        foreach (self::SHIPPING_COUNTRY_CODES as $code) {
            $countries[$code] = Countries::getName($code, $locale);
        }

        \asort($countries, \SORT_LOCALE_STRING);

        return $countries;
    }
}
