<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Service\CartManager;
use App\Service\CurrencyConverter;
use App\Service\OrderMailer;
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
        private readonly LoggerInterface $logger,
        private readonly string $stripePublicKey,
    ) {
    }

    #[Route(
        path: '/checkout',
        name: 'shop_checkout',
        methods: ['GET', 'POST'],
        requirements: ['_locale' => 'en'],
    )]
    #[Route(
        path: '/paiement',
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

        $subtotalUsd = $this->cartManager->getSubtotalUsd();
        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);
        $locale = $request->getLocale();

        $errors = [];

        if ($request->isMethod('POST')) {
            $errors = $this->validateCheckoutForm($request);

            if ([] === $errors) {
                $order = $this->createOrder($request, $products, $subtotalUsd);

                $this->entityManager->persist($order);
                $this->entityManager->flush();

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

        // Mark order as processing
        $order->setStripePaymentStatus($paymentIntent->status);
        $order->setStatus(OrderStatus::Processing);

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

        // Clear cart and pending order from session
        $this->cartManager->clear();
        $request->getSession()->remove('_pending_order');
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

    /**
     * @return array<string, string>
     */
    private function validateCheckoutForm(Request $request): array
    {
        $errors = [];

        $name = \trim((string) $request->request->get('customer_name', ''));
        $email = \trim((string) $request->request->get('customer_email', ''));
        $line1 = \trim((string) $request->request->get('address_line1', ''));
        $city = \trim((string) $request->request->get('city', ''));
        $postalCode = \trim((string) $request->request->get('postal_code', ''));
        $country = \trim((string) $request->request->get('country', ''));

        if ('' === $name) {
            $errors['customer_name'] = 'checkout.error.name_required';
        }

        if ('' === $email || false === \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            $errors['customer_email'] = 'checkout.error.email_invalid';
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

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private function getFormData(Request $request): array
    {
        // On POST, use submitted data
        if ($request->isMethod('POST')) {
            return [
                'customer_name' => \trim((string) $request->request->get('customer_name', '')),
                'customer_email' => \trim((string) $request->request->get('customer_email', '')),
                'address_line1' => \trim((string) $request->request->get('address_line1', '')),
                'address_line2' => \trim((string) $request->request->get('address_line2', '')),
                'city' => \trim((string) $request->request->get('city', '')),
                'state' => \trim((string) $request->request->get('state', '')),
                'postal_code' => \trim((string) $request->request->get('postal_code', '')),
                'country' => \trim((string) $request->request->get('country', '')),
            ];
        }

        // On GET, pre-fill from logged-in customer's default address
        $user = $this->getUser();
        if ($user instanceof Customer) {
            $address = $user->getDefaultAddress();

            return [
                'customer_name' => $user->getFullName(),
                'customer_email' => $user->getEmail(),
                'address_line1' => $address?->getAddressLine1() ?? '',
                'address_line2' => $address?->getAddressLine2() ?? '',
                'city' => $address?->getCity() ?? '',
                'state' => $address?->getState() ?? '',
                'postal_code' => $address?->getPostalCode() ?? '',
                'country' => $address?->getCountry() ?? '',
            ];
        }

        return [
            'customer_name' => '',
            'customer_email' => '',
            'address_line1' => '',
            'address_line2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => '',
        ];
    }

    /**
     * @param \App\Entity\Product[] $products
     */
    private function createOrder(Request $request, array $products, float $subtotalUsd): Order
    {
        $order = new Order();
        $order->setReference(Order::generateReference());
        $order->setCustomerLocale($request->getLocale());
        $order->setCustomerName(\trim((string) $request->request->get('customer_name', '')));
        $order->setCustomerEmail(\trim((string) $request->request->get('customer_email', '')));
        $order->setShippingAddressLine1(\trim((string) $request->request->get('address_line1', '')));
        $order->setShippingAddressLine2(\trim((string) $request->request->get('address_line2', '')) ?: null);
        $order->setShippingCity(\trim((string) $request->request->get('city', '')));
        $order->setShippingState(\trim((string) $request->request->get('state', '')) ?: null);
        $order->setShippingPostalCode(\trim((string) $request->request->get('postal_code', '')));
        $order->setShippingCountry(\trim((string) $request->request->get('country', '')));
        $order->setTotalUsd($subtotalUsd);

        // Link order to customer if logged in
        $user = $this->getUser();
        if ($user instanceof Customer) {
            $order->setCustomer($user);
        }

        foreach ($products as $product) {
            $shippingCost = $this->shippingCostProvider->getCost($product->getShippingTier());
            $item = OrderItem::fromProduct($product, $shippingCost);
            $order->addItem($item);
        }

        return $order;
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
