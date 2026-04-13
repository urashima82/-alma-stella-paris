<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Enum\OrderStatus;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Service\CartManager;
use App\Service\CurrencyConverter;
use App\Service\OrderMailer;
use App\Service\ReservationManager;
use App\Service\ShippingCostProvider;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Routing\Attribute\Route;

class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CartManager $cartManager,
        private readonly CurrencyConverter $currencyConverter,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
        private readonly ShippingCostProvider $shippingCostProvider,
        private readonly StripeService $stripeService,
        private readonly OrderMailer $orderMailer,
        private readonly ReservationManager $reservationManager,
        private readonly LoggerInterface $logger,
        private readonly string $stripePublicKey,
    ) {
    }

    #[Route(
        path: '/identify',
        name: 'shop_checkout_identify',
        methods: ['GET', 'POST'],
        requirements: ['_locale' => 'en'],
    )]
    #[Route(
        path: '/identification',
        name: 'shop_checkout_identify_fr',
        methods: ['GET', 'POST'],
        requirements: ['_locale' => 'fr'],
    )]
    public function identify(Request $request): Response
    {
        $products = $this->cartManager->getProducts();

        if ([] === $products) {
            return $this->redirectToRoute('shop_catalog', ['_locale' => $request->getLocale()]);
        }

        // Reserve all cart products on checkout entry
        $this->reserveCartProducts($products);

        // Logged-in customers skip identification
        if ($this->getUser() instanceof Customer) {
            return $this->redirectToRoute('shop_checkout', ['_locale' => $request->getLocale()]);
        }

        // Guest continues without account — store email in session
        if ($request->isMethod('POST') && $request->request->has('guest_email')) {
            $email = \trim((string) $request->request->get('guest_email', ''));

            if ('' !== $email && false !== \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                $request->getSession()->set('_checkout_email', $email);

                return $this->redirectToRoute('shop_checkout', ['_locale' => $request->getLocale()]);
            }
        }

        return $this->render('shop/checkout/identify.html.twig', [
            'reservationSeconds' => $this->reservationManager->getRemainingSeconds(),
        ]);
    }

    #[Route(
        path: '/checkout',
        name: 'shop_checkout',
        methods: ['GET', 'POST'],
        requirements: ['_locale' => 'en'],
    )]
    #[Route(
        path: '/livraison',
        name: 'shop_checkout_fr',
        methods: ['GET', 'POST'],
        requirements: ['_locale' => 'fr'],
    )]
    public function checkout(Request $request): Response
    {
        $products = $this->cartManager->getProducts();

        if ([] === $products) {
            return $this->redirectToRoute('shop_catalog', ['_locale' => $request->getLocale()]);
        }

        // Guests must go through identification first
        if (!$this->getUser() instanceof Customer && !$request->getSession()->has('_checkout_email')) {
            return $this->redirectToRoute('shop_checkout_identify', ['_locale' => $request->getLocale()]);
        }

        // Reserve all cart products (extends existing reservations on re-entry)
        $this->reserveCartProducts($products);

        $subtotalUsd = $this->cartManager->getSubtotalUsd();
        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);
        $locale = $request->getLocale();

        $errors = [];

        if ($request->isMethod('POST')) {
            $errors = $this->validateCheckoutForm($request);

            if ([] === $errors) {
                // Reuse existing Pending order if available, otherwise create new
                $pendingRef = $request->getSession()->get('_pending_order');
                $existingOrder = null !== $pendingRef
                    ? $this->orderRepository->findByReference($pendingRef)
                    : null;

                if (null !== $existingOrder && OrderStatus::Pending === $existingOrder->getStatus()) {
                    $order = $this->updateExistingOrder($existingOrder, $request, $products, $subtotalUsd);
                } else {
                    $order = $this->createOrder($request, $products, $subtotalUsd);
                    $this->entityManager->persist($order);
                }

                $this->entityManager->flush();

                // Save shipping address to address book for logged-in customers
                $this->saveNewAddressIfNeeded($request);

                // Store order reference in session for payment step
                $request->getSession()->set('_pending_order', $order->getReference());

                return $this->redirectToRoute('shop_payment', ['_locale' => $locale]);
            }
        }

        // Build cart items for display
        $items = [];
        foreach ($products as $product) {
            $displayPrice = $this->shippingCostProvider->getDisplayPrice($product->getBasePrice(), $product->getShippingTier());
            $items[] = [
                'product' => $product,
                'priceConverted' => $this->currencyConverter->convert($displayPrice, $currency),
            ];
        }

        // Build address data for logged-in customer's address selector
        $customer = $this->getUser();
        $customerAddresses = [];
        if ($customer instanceof Customer) {
            foreach ($customer->getAddresses() as $address) {
                $customerAddresses[] = [
                    'id' => $address->getId(),
                    'label' => $address->getLabel(),
                    'recipientName' => $address->getRecipientName() ?? '',
                    'addressLine1' => $address->getAddressLine1(),
                    'addressLine2' => $address->getAddressLine2() ?? '',
                    'city' => $address->getCity(),
                    'state' => $address->getState() ?? '',
                    'postalCode' => $address->getPostalCode(),
                    'country' => $address->getCountry(),
                    'isDefault' => $address->isDefault(),
                ];
            }
        }

        return $this->render('shop/checkout/index.html.twig', [
            'items' => $items,
            'subtotalUsd' => $subtotalUsd,
            'subtotalConverted' => $this->currencyConverter->convert($subtotalUsd, $currency),
            'currency' => $currency,
            'errors' => $errors,
            'formData' => $this->getFormData($request),
            'countries' => self::getShippingCountries($request->getLocale()),
            'customerAddresses' => $customerAddresses,
            'reservationSeconds' => $this->reservationManager->getRemainingSeconds(),
        ]);
    }

    #[Route(
        path: '/payment',
        name: 'shop_payment',
        methods: ['GET'],
        requirements: ['_locale' => 'en'],
    )]
    #[Route(
        path: '/paiement/carte',
        name: 'shop_payment_fr',
        methods: ['GET'],
        requirements: ['_locale' => 'fr'],
    )]
    public function payment(Request $request): Response
    {
        $orderRef = $request->getSession()->get('_pending_order');

        if (null === $orderRef) {
            return $this->redirectToRoute('shop_checkout', ['_locale' => $request->getLocale()]);
        }

        $order = $this->orderRepository->findByReference($orderRef);

        if (null === $order) {
            return $this->redirectToRoute('shop_checkout', ['_locale' => $request->getLocale()]);
        }

        // Create or reuse Stripe PaymentIntent
        $paymentIntentId = $order->getStripePaymentIntentId();

        try {
            if (null !== $paymentIntentId) {
                $paymentIntent = $this->stripeService->retrievePaymentIntent($paymentIntentId);
            } else {
                $paymentIntent = $this->stripeService->createPaymentIntent($order);
                $order->setStripePaymentIntentId($paymentIntent->id);
                $this->entityManager->flush();
            }
        } catch (\Exception $e) {
            $this->logger->error('Stripe PaymentIntent error: {message}', ['message' => $e->getMessage()]);
            $this->addFlash('error', 'checkout.payment_error');

            return $this->redirectToRoute('shop_checkout', ['_locale' => $request->getLocale()]);
        }

        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);

        return $this->render('shop/checkout/payment.html.twig', [
            'order' => $order,
            'totalConverted' => $this->currencyConverter->convert($order->getTotalUsd(), $currency),
            'currency' => $currency,
            'stripePublicKey' => $this->stripePublicKey,
            'clientSecret' => $paymentIntent->client_secret,
            'reservationSeconds' => $this->reservationManager->getRemainingSeconds(),
        ]);
    }

    #[Route(
        path: '/payment/confirm',
        name: 'shop_payment_confirm',
        methods: ['POST'],
        requirements: ['_locale' => 'en'],
    )]
    #[Route(
        path: '/paiement/confirmer',
        name: 'shop_payment_confirm_fr',
        methods: ['POST'],
        requirements: ['_locale' => 'fr'],
    )]
    public function confirmPayment(Request $request): JsonResponse
    {
        $orderRef = $request->getSession()->get('_pending_order');

        if (null === $orderRef) {
            return new JsonResponse(['error' => 'no_pending_order'], Response::HTTP_BAD_REQUEST);
        }

        $order = $this->orderRepository->findByReference($orderRef);

        if (null === $order || null === $order->getStripePaymentIntentId()) {
            return new JsonResponse(['error' => 'order_not_found'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $paymentIntent = $this->stripeService->retrievePaymentIntent($order->getStripePaymentIntentId());
        } catch (\Exception $e) {
            $this->logger->error('Stripe retrieve error: {message}', ['message' => $e->getMessage()]);

            return new JsonResponse(['error' => 'payment_verification_failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ('succeeded' !== $paymentIntent->status) {
            return new JsonResponse(['error' => 'payment_not_completed', 'status' => $paymentIntent->status], Response::HTTP_BAD_REQUEST);
        }

        // Mark order as processing and assign invoice number
        $order->setStripePaymentStatus($paymentIntent->status);
        $order->setStatus(OrderStatus::Processing);
        $order->setPaidAt(new \DateTimeImmutable());
        $order->setInvoiceNumber($this->orderRepository->nextInvoiceNumber((int) \date('Y')));

        // Mark purchased products as sold (setIsSoldOut auto-sets soldAt)
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if (null !== $product && !$product->isSoldOut()) {
                $product->setIsSoldOut(true);
            }
        }

        $this->entityManager->flush();

        // Send confirmation email
        $locale = $request->getLocale();

        try {
            $this->orderMailer->sendOrderConfirmation($order, $locale);
        } catch (\Exception $e) {
            $this->logger->error('Order confirmation email failed: {message}', ['message' => $e->getMessage()]);
        }

        try {
            $this->orderMailer->sendNewOrderAdminNotification($order);
        } catch (\Exception $e) {
            $this->logger->error('Admin notification email failed: {message}', ['message' => $e->getMessage()]);
        }

        // Release reservations for purchased products
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if (null !== $product) {
                $this->reservationManager->release($product);
            }
        }

        // Clear cart and checkout session data
        $this->cartManager->clear();
        $request->getSession()->remove('_pending_order');
        $request->getSession()->remove('_checkout_email');
        $confirmationRoute = 'fr' === $locale ? 'shop_order_confirmation_fr' : 'shop_order_confirmation';

        return new JsonResponse([
            'success' => true,
            'redirectUrl' => $this->generateUrl($confirmationRoute, [
                '_locale' => $locale,
                'reference' => $order->getReference(),
            ]),
        ]);
    }

    #[Route(
        path: '/order/{reference}/confirmation',
        name: 'shop_order_confirmation',
        methods: ['GET'],
        requirements: ['_locale' => 'en'],
    )]
    #[Route(
        path: '/commande/{reference}/confirmation',
        name: 'shop_order_confirmation_fr',
        methods: ['GET'],
        requirements: ['_locale' => 'fr'],
    )]
    public function confirmation(string $reference, Request $request): Response
    {
        $order = $this->orderRepository->findByReference($reference);

        if (null === $order) {
            throw $this->createNotFoundException();
        }

        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);

        return $this->render('shop/checkout/confirmation.html.twig', [
            'order' => $order,
            'totalConverted' => $this->currencyConverter->convert($order->getTotalUsd(), $currency),
            'currency' => $currency,
        ]);
    }

    #[Route(
        path: '/order/{reference}/tracking',
        name: 'shop_order_tracking',
        methods: ['GET'],
        requirements: ['_locale' => 'en'],
    )]
    #[Route(
        path: '/commande/{reference}/suivi',
        name: 'shop_order_tracking_fr',
        methods: ['GET'],
        requirements: ['_locale' => 'fr'],
    )]
    public function tracking(string $reference, Request $request): Response
    {
        $order = $this->orderRepository->findByReference($reference);

        if (null === $order) {
            throw $this->createNotFoundException();
        }

        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);

        return $this->render('shop/checkout/tracking.html.twig', [
            'order' => $order,
            'totalConverted' => $this->currencyConverter->convert($order->getTotalUsd(), $currency),
            'currency' => $currency,
        ]);
    }

    #[Route(
        path: '/checkout/email-check',
        name: 'shop_checkout_email_check',
        methods: ['POST'],
    )]
    public function emailCheck(Request $request, CustomerRepository $customerRepository): JsonResponse
    {
        $email = \trim((string) $request->getPayload()->get('email', ''));

        if ('' === $email || false === \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['exists' => false]);
        }

        return new JsonResponse(['exists' => null !== $customerRepository->findByEmail($email)]);
    }

    /**
     * @return array<string, string>
     */
    private function validateCheckoutForm(Request $request): array
    {
        $errors = [];

        $email = \trim((string) $request->request->get('customer_email', ''));
        $shippingRecipient = \trim((string) $request->request->get('shipping_recipient_name', ''));
        $line1 = \trim((string) $request->request->get('address_line1', ''));
        $city = \trim((string) $request->request->get('city', ''));
        $postalCode = \trim((string) $request->request->get('postal_code', ''));
        $country = \trim((string) $request->request->get('country', ''));

        if ('' === $email || false === \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $errors['customer_email'] = 'checkout.error.email_invalid';
        }

        if ('' === $shippingRecipient) {
            $errors['shipping_recipient_name'] = 'checkout.error.shipping_recipient_required';
        }

        if ('' === $line1) {
            $errors['address_line1'] = 'checkout.error.address_required';
        }

        if ('' === $city) {
            $errors['city'] = 'checkout.error.city_required';
        }

        if ('' === $postalCode) {
            $errors['postal_code'] = 'checkout.error.postal_code_required';
        }

        if ('' === $country || 2 !== \strlen($country)) {
            $errors['country'] = 'checkout.error.country_required';
        }

        // Validate billing address if different from shipping
        if ($request->request->has('billing_different')) {
            $billingRecipient = \trim((string) $request->request->get('billing_recipient_name', ''));
            $billingLine1 = \trim((string) $request->request->get('billing_address_line1', ''));
            $billingCity = \trim((string) $request->request->get('billing_city', ''));
            $billingPostalCode = \trim((string) $request->request->get('billing_postal_code', ''));
            $billingCountry = \trim((string) $request->request->get('billing_country', ''));

            if ('' === $billingRecipient) {
                $errors['billing_recipient_name'] = 'checkout.error.billing_recipient_required';
            }

            if ('' === $billingLine1) {
                $errors['billing_address_line1'] = 'checkout.error.billing_address_required';
            }

            if ('' === $billingCity) {
                $errors['billing_city'] = 'checkout.error.billing_city_required';
            }

            if ('' === $billingPostalCode) {
                $errors['billing_postal_code'] = 'checkout.error.billing_postal_code_required';
            }

            if ('' === $billingCountry || 2 !== \strlen($billingCountry)) {
                $errors['billing_country'] = 'checkout.error.billing_country_required';
            }
        }

        return $errors;
    }

    /**
     * @return array<string, mixed>
     */
    private function getFormData(Request $request): array
    {
        $billingDefaults = [
            'billing_different' => false,
            'billing_recipient_name' => '',
            'billing_address_line1' => '',
            'billing_address_line2' => '',
            'billing_city' => '',
            'billing_state' => '',
            'billing_postal_code' => '',
            'billing_country' => '',
        ];

        // On POST, use submitted data
        if ($request->isMethod('POST')) {
            return [
                'customer_email' => \trim((string) $request->request->get('customer_email', '')),
                'shipping_recipient_name' => \trim((string) $request->request->get('shipping_recipient_name', '')),
                'address_line1' => \trim((string) $request->request->get('address_line1', '')),
                'address_line2' => \trim((string) $request->request->get('address_line2', '')),
                'city' => \trim((string) $request->request->get('city', '')),
                'state' => \trim((string) $request->request->get('state', '')),
                'postal_code' => \trim((string) $request->request->get('postal_code', '')),
                'country' => \trim((string) $request->request->get('country', '')),
                'billing_different' => $request->request->has('billing_different'),
                'billing_recipient_name' => \trim((string) $request->request->get('billing_recipient_name', '')),
                'billing_address_line1' => \trim((string) $request->request->get('billing_address_line1', '')),
                'billing_address_line2' => \trim((string) $request->request->get('billing_address_line2', '')),
                'billing_city' => \trim((string) $request->request->get('billing_city', '')),
                'billing_state' => \trim((string) $request->request->get('billing_state', '')),
                'billing_postal_code' => \trim((string) $request->request->get('billing_postal_code', '')),
                'billing_country' => \trim((string) $request->request->get('billing_country', '')),
            ];
        }

        // On GET, pre-fill from logged-in customer's default address
        $user = $this->getUser();
        if ($user instanceof Customer) {
            $address = $user->getDefaultAddress();

            return [
                'customer_email' => $user->getEmail(),
                'shipping_recipient_name' => $address?->getRecipientName() ?? $user->getFullName(),
                'address_line1' => $address?->getAddressLine1() ?? '',
                'address_line2' => $address?->getAddressLine2() ?? '',
                'city' => $address?->getCity() ?? '',
                'state' => $address?->getState() ?? '',
                'postal_code' => $address?->getPostalCode() ?? '',
                'country' => $address?->getCountry() ?? '',
                ...$billingDefaults,
            ];
        }

        // Pre-fill guest email from identification step
        $guestEmail = (string) $request->getSession()->get('_checkout_email', '');

        return [
            'customer_email' => $guestEmail,
            'shipping_recipient_name' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => '',
            ...$billingDefaults,
        ];
    }

    /**
     * @param Product[] $products
     */
    private function updateExistingOrder(Order $order, Request $request, array $products, float $subtotalUsd): Order
    {
        $this->fillOrderFromRequest($order, $request);

        // Replace items if cart changed
        $oldTotal = $order->getTotalUsd();
        $order->setTotalUsd($subtotalUsd);

        foreach ($order->getItems()->toArray() as $oldItem) {
            $order->removeItem($oldItem);
        }

        foreach ($products as $product) {
            $shippingCost = $this->shippingCostProvider->getCost($product->getShippingTier());
            $item = OrderItem::fromProduct($product, $shippingCost);
            $order->addItem($item);
        }

        // Reset Stripe PaymentIntent if total changed (new PI will be created at payment step)
        if ($oldTotal !== $subtotalUsd && null !== $order->getStripePaymentIntentId()) {
            $order->setStripePaymentIntentId(null);
        }

        return $order;
    }

    /**
     * @param Product[] $products
     */
    private function createOrder(Request $request, array $products, float $subtotalUsd): Order
    {
        $order = new Order();
        $order->setReference(Order::generateReference());
        $this->fillOrderFromRequest($order, $request);
        $order->setTotalUsd($subtotalUsd);

        foreach ($products as $product) {
            $shippingCost = $this->shippingCostProvider->getCost($product->getShippingTier());
            $item = OrderItem::fromProduct($product, $shippingCost);
            $order->addItem($item);
        }

        return $order;
    }

    private function fillOrderFromRequest(Order $order, Request $request): void
    {
        $shippingRecipient = \trim((string) $request->request->get('shipping_recipient_name', ''));

        $order->setCustomerLocale($request->getLocale());
        $order->setCustomerEmail(\trim((string) $request->request->get('customer_email', '')));
        $order->setShippingRecipientName($shippingRecipient);
        $order->setShippingAddressLine1(\trim((string) $request->request->get('address_line1', '')));
        $order->setShippingAddressLine2(\trim((string) $request->request->get('address_line2', '')) ?: null);
        $order->setShippingCity(\trim((string) $request->request->get('city', '')));
        $order->setShippingState(\trim((string) $request->request->get('state', '')) ?: null);
        $order->setShippingPostalCode(\trim((string) $request->request->get('postal_code', '')));
        $order->setShippingCountry(\trim((string) $request->request->get('country', '')));

        // Billing address (null if same as shipping)
        if ($request->request->has('billing_different')) {
            $order->setBillingRecipientName(\trim((string) $request->request->get('billing_recipient_name', '')));
            $order->setBillingAddressLine1(\trim((string) $request->request->get('billing_address_line1', '')));
            $order->setBillingAddressLine2(\trim((string) $request->request->get('billing_address_line2', '')) ?: null);
            $order->setBillingCity(\trim((string) $request->request->get('billing_city', '')));
            $order->setBillingState(\trim((string) $request->request->get('billing_state', '')) ?: null);
            $order->setBillingPostalCode(\trim((string) $request->request->get('billing_postal_code', '')));
            $order->setBillingCountry(\trim((string) $request->request->get('billing_country', '')));
        } else {
            $order->setBillingRecipientName(null);
            $order->setBillingAddressLine1(null);
            $order->setBillingAddressLine2(null);
            $order->setBillingCity(null);
            $order->setBillingState(null);
            $order->setBillingPostalCode(null);
            $order->setBillingCountry(null);
        }

        // Link order to customer if logged in, and derive customerName
        $user = $this->getUser();
        if ($user instanceof Customer) {
            $order->setCustomer($user);
            $order->setCustomerName($user->getFullName());
        } else {
            $order->setCustomerName($shippingRecipient);
        }
    }

    private function saveNewAddressIfNeeded(Request $request): void
    {
        $user = $this->getUser();

        if (!$user instanceof Customer) {
            return;
        }

        $line1 = \trim((string) $request->request->get('address_line1', ''));
        $postalCode = \trim((string) $request->request->get('postal_code', ''));
        $country = \trim((string) $request->request->get('country', ''));

        // Check if this address already exists in the customer's address book
        foreach ($user->getAddresses() as $existing) {
            if ($existing->getAddressLine1() === $line1
                && $existing->getPostalCode() === $postalCode
                && \strtoupper($existing->getCountry()) === \strtoupper($country)
            ) {
                return;
            }
        }

        // Save as new address
        $address = new CustomerAddress();
        $address->setCustomer($user);
        $address->setLabel($this->generateAddressLabel($user));
        $address->setRecipientName(\trim((string) $request->request->get('shipping_recipient_name', '')) ?: null);
        $address->setAddressLine1($line1);
        $address->setAddressLine2(\trim((string) $request->request->get('address_line2', '')) ?: null);
        $address->setCity(\trim((string) $request->request->get('city', '')));
        $address->setState(\trim((string) $request->request->get('state', '')) ?: null);
        $address->setPostalCode($postalCode);
        $address->setCountry($country);

        // Set as default if customer has no addresses yet
        if ($user->getAddresses()->isEmpty()) {
            $address->setIsDefault(true);
        }

        $this->entityManager->persist($address);
        $this->entityManager->flush();
    }

    private function generateAddressLabel(Customer $customer): string
    {
        $count = $customer->getAddresses()->count();

        if (0 === $count) {
            return 'Home';
        }

        return 'Address '.($count + 1);
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

    /**
     * @param Product[] $products
     */
    private function reserveCartProducts(array $products): void
    {
        foreach ($products as $product) {
            $this->reservationManager->reserve($product);
        }
    }
}
