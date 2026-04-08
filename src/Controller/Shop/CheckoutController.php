<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Repository\OrderRepository;
use App\Service\CartManager;
use App\Service\CurrencyConverter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CartManager $cartManager,
        private readonly CurrencyConverter $currencyConverter,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
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
            $displayPrice = $product->getDisplayPrice();
            $items[] = [
                'product' => $product,
                'priceConverted' => $this->currencyConverter->convert($displayPrice, $currency),
            ];
        }

        return $this->render('shop/checkout/index.html.twig', [
            'items' => $items,
            'subtotalUsd' => $subtotalUsd,
            'subtotalConverted' => $this->currencyConverter->convert($subtotalUsd, $currency),
            'currency' => $currency,
            'errors' => $errors,
            'formData' => $this->getFormData($request),
            'countries' => self::getShippingCountries(),
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

        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);

        return $this->render('shop/checkout/payment.html.twig', [
            'order' => $order,
            'totalConverted' => $this->currencyConverter->convert($order->getTotalUsd(), $currency),
            'currency' => $currency,
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

    /**
     * @param \App\Entity\Product[] $products
     */
    private function createOrder(Request $request, array $products, float $subtotalUsd): Order
    {
        $order = new Order();
        $order->setReference(Order::generateReference());
        $order->setCustomerName(\trim((string) $request->request->get('customer_name', '')));
        $order->setCustomerEmail(\trim((string) $request->request->get('customer_email', '')));
        $order->setShippingAddressLine1(\trim((string) $request->request->get('address_line1', '')));
        $order->setShippingAddressLine2(\trim((string) $request->request->get('address_line2', '')) ?: null);
        $order->setShippingCity(\trim((string) $request->request->get('city', '')));
        $order->setShippingState(\trim((string) $request->request->get('state', '')) ?: null);
        $order->setShippingPostalCode(\trim((string) $request->request->get('postal_code', '')));
        $order->setShippingCountry(\trim((string) $request->request->get('country', '')));
        $order->setTotalUsd($subtotalUsd);

        foreach ($products as $product) {
            $item = OrderItem::fromProduct($product);
            $order->addItem($item);
        }

        return $order;
    }

    /**
     * @return array<string, string>
     */
    private static function getShippingCountries(): array
    {
        return [
            'US' => 'United States',
            'CA' => 'Canada',
            'FR' => 'France',
            'GB' => 'United Kingdom',
            'MX' => 'Mexico',
            'DE' => 'Germany',
            'ES' => 'Spain',
            'IT' => 'Italy',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'PT' => 'Portugal',
            'IE' => 'Ireland',
            'AU' => 'Australia',
            'NZ' => 'New Zealand',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'SG' => 'Singapore',
            'AE' => 'United Arab Emirates',
            'BR' => 'Brazil',
            'CO' => 'Colombia',
            'CL' => 'Chile',
            'AR' => 'Argentina',
            'SE' => 'Sweden',
            'DK' => 'Denmark',
            'NO' => 'Norway',
            'FI' => 'Finland',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'GR' => 'Greece',
            'IL' => 'Israel',
            'TH' => 'Thailand',
            'MY' => 'Malaysia',
            'PH' => 'Philippines',
            'IN' => 'India',
        ];
    }
}
