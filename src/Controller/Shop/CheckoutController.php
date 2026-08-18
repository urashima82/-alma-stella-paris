<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Entity\Customer;
use App\Entity\CustomerAddress;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Enum\OrderConfirmationOutcome;
use App\Enum\OrderStatus;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Service\CartManager;
use App\Service\CurrencyConverter;
use App\Service\OrderConfirmer;
use App\Service\PromotionEngine;
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
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CartManager $cartManager,
        private readonly CurrencyConverter $currencyConverter,
        private readonly EntityManagerInterface $entityManager,
        private readonly OrderRepository $orderRepository,
        private readonly ShippingCostProvider $shippingCostProvider,
        private readonly StripeService $stripeService,
        private readonly ReservationManager $reservationManager,
        private readonly PromotionEngine $promotionEngine,
        private readonly OrderConfirmer $orderConfirmer,
        private readonly LockFactory $lockFactory,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
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
        $this->flashRemovedItemsWarning($request);

        if ($products === []) {
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

            if ($email !== '' && \filter_var($email, \FILTER_VALIDATE_EMAIL) !== false) {
                $request->getSession()->set('_checkout_email', $email);

                return $this->redirectToRoute('shop_checkout', ['_locale' => $request->getLocale()]);
            }

            // Server-side feedback for the no-JS fallback (the normal flow
            // validates the email client-side before submitting).
            $this->addFlash('warning', $this->translator->trans('identify.error.email_invalid'));
        }

        return $this->render('shop/checkout/identify.html.twig', [
            'reservationSeconds' => $this->reservationManager->getRemainingSeconds(),
            'extensionsLeft' => $this->reservationManager->getExtensionsLeftForCurrentSession(),
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
        $this->flashRemovedItemsWarning($request);

        if ($products === []) {
            return $this->redirectToRoute('shop_catalog', ['_locale' => $request->getLocale()]);
        }

        // Guests must go through identification first
        if (!$this->getUser() instanceof Customer && !$request->getSession()->has('_checkout_email')) {
            return $this->redirectToRoute('shop_checkout_identify', ['_locale' => $request->getLocale()]);
        }

        // Reserve all cart products (extends existing reservations on re-entry)
        $this->reserveCartProducts($products);

        $effectiveSubtotalEur = $this->promotionEngine->getEffectiveSubtotalEur($products);
        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);
        $locale = $request->getLocale();

        $errors = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('submit', $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $errors = $this->validateCheckoutForm($request);

            if ($errors === []) {
                // Evaluate promotions for the order. The submitted email is
                // authoritative: it is what gets recorded on the order and in
                // PromotionUsage, so per-email limits must be checked against
                // it — not against the identification-step session email.
                $couponCodeForOrder = (string) $request->request->get('coupon_code', '');
                $emailForOrder = \trim((string) $request->request->get('customer_email', ''));
                $promoResult = $this->promotionEngine->evaluateCartPromotions($products, $effectiveSubtotalEur, $couponCodeForOrder, $emailForOrder);
                $shippingSurchargeEur = $this->computeShippingSurchargeEur(
                    \trim((string) $request->request->get('country', '')),
                    \trim((string) $request->request->get('postal_code', '')),
                    $products,
                );
                // Free orders are not supported: clamp the discount so the
                // total stays chargeable by Stripe (minimum 0,50 €).
                $orderDiscountEur = $this->clampDiscount($promoResult['totalDiscount'], $effectiveSubtotalEur + $shippingSurchargeEur);
                $orderFinalTotal = $effectiveSubtotalEur + $shippingSurchargeEur - $orderDiscountEur;
                // The per-promotion shares must add up to the clamped total —
                // they feed the PDF invoice lines and PromotionUsage.
                $promotionShares = $this->scalePromotionShares($promoResult['promotions'], $promoResult['totalDiscount'], $orderDiscountEur);

                // Reuse existing Pending order if available, otherwise create new
                $pendingRef = $request->getSession()->get('_pending_order');
                $existingOrder = $pendingRef !== null
                    ? $this->orderRepository->findByReference($pendingRef)
                    : null;

                $isNewOrder = false;

                if ($existingOrder !== null && $existingOrder->getStatus() === OrderStatus::Pending) {
                    $order = $this->updateExistingOrder($existingOrder, $request, $products, $orderFinalTotal);
                } else {
                    $order = $this->createOrder($request, $products, $orderFinalTotal);
                    $this->entityManager->persist($order);
                    $isNewOrder = true;
                }

                // Store discount and surcharge info on order
                $order->setDiscountAmountEur($orderDiscountEur);
                $order->setShippingSurchargeEur($shippingSurchargeEur);
                $order->setPromotionCode($couponCodeForOrder !== '' ? \strtoupper($couponCodeForOrder) : null);
                $order->setAppliedPromotions(\array_map(
                    static fn (array $entry) => [
                        'name' => $entry['promotion']->getName(),
                        'nameFr' => $entry['promotion']->getNameFr(),
                        'discount' => $entry['discount'],
                        'code' => $entry['promotion']->getCode(),
                    ],
                    $promotionShares,
                ));

                // Store promo result in session for tracking on payment confirmation
                $request->getSession()->set('_order_promotions', \array_map(
                    static fn (array $entry) => ['id' => $entry['promotion']->getId(), 'discount' => $entry['discount']],
                    $promotionShares,
                ));

                if ($isNewOrder) {
                    // Process-level lock + locked read in one transaction: the
                    // DB gap locks alone do not conflict on an empty prefix
                    // (the year's first order), so two concurrent checkouts
                    // could otherwise mint the same reference.
                    $sequenceLock = $this->lockFactory->createLock('order-reference-sequence', ttl: 10.0);
                    $sequenceLock->acquire(blocking: true);

                    try {
                        $this->entityManager->wrapInTransaction(function () use ($order): void {
                            $order->setReference($this->orderRepository->nextOrderReference((int) \date('Y'), lock: true));
                            $this->entityManager->flush();
                        });
                    } finally {
                        $sequenceLock->release();
                    }
                } else {
                    $this->entityManager->flush();
                }

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
            $promoPrice = $this->promotionEngine->getDiscountedDisplayPrice($product);
            $effectivePrice = $promoPrice ?? $displayPrice;
            $compareAtPrice = $this->promotionEngine->getEffectiveCompareAtPrice($product);

            $item = [
                'product' => $product,
                'priceConverted' => $this->currencyConverter->convert($effectivePrice, $currency),
            ];

            if ($compareAtPrice !== null) {
                $item['compareAtPriceConverted'] = $this->currencyConverter->convert($compareAtPrice, $currency);
            }

            $items[] = $item;
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

        // Evaluate coupon code from session
        $couponCode = $request->getSession()->get('_promo_code');
        $customerEmail = $this->getCheckoutEmail($request);
        $cartPromoResult = $this->promotionEngine->evaluateCartPromotions($products, $effectiveSubtotalEur, $couponCode, $customerEmail);
        $discountAmountEur = $this->clampDiscount($cartPromoResult['totalDiscount'], $effectiveSubtotalEur);
        $finalTotalEur = $effectiveSubtotalEur - $discountAmountEur;

        // Build applied discounts list for template (with clear labels)
        $appliedDiscounts = [];
        foreach ($cartPromoResult['promotions'] as $entry) {
            /** @var \App\Entity\Promotion $promo */
            $promo = $entry['promotion'];
            $discount = (float) $entry['discount'];

            $localizedName = $locale === 'fr' && $promo->getNameFr() !== ''
                ? $promo->getNameFr()
                : $promo->getName();
            $appliedDiscounts[] = [
                'label' => $promo->getCode() !== null
                    ? \sprintf('%s (%s)', $localizedName, $promo->getCode())
                    : $localizedName,
                'amountConverted' => $this->currencyConverter->convert($discount, $currency),
            ];
        }

        // Build applied coupon info independently (for coupon input UI state)
        $appliedCoupon = null;
        if ($couponCode !== null && $couponCode !== '') {
            $validatedPromo = $this->promotionEngine->validateCouponCode($couponCode, $effectiveSubtotalEur, $customerEmail);
            if ($validatedPromo !== null) {
                $appliedCoupon = [
                    'code' => $couponCode,
                    'label' => $validatedPromo->getDiscountLabel(),
                ];
            } else {
                // The stored coupon is no longer valid (expired, deactivated,
                // minimum no longer met). Purge it — a stale session code can
                // never reach the order (the not-applied branch submits an
                // empty coupon_code), so isOrderStale() would otherwise bounce
                // the customer between checkout and payment forever.
                $request->getSession()->remove('_promo_code');
            }
        }

        return $this->render('shop/checkout/index.html.twig', [
            'items' => $items,
            'subtotalEur' => $effectiveSubtotalEur,
            'subtotalConverted' => $this->currencyConverter->convert($effectiveSubtotalEur, $currency),
            'discountAmountEur' => $discountAmountEur,
            'discountConverted' => $this->currencyConverter->convert($discountAmountEur, $currency),
            'finalTotalConverted' => $this->currencyConverter->convert($finalTotalEur, $currency),
            // Out-of-zone surcharge preview: amounts are precomputed so the
            // summary can switch lines client-side when the country changes;
            // the authoritative amount is recomputed server-side on POST.
            'surchargeConverted' => $this->currencyConverter->convert($this->sumShippingCosts($products), $currency),
            'finalTotalWithSurchargeConverted' => $this->currencyConverter->convert($finalTotalEur + $this->sumShippingCosts($products), $currency),
            'includedZoneCountries' => self::INCLUDED_ZONE_COUNTRY_CODES,
            'currency' => $currency,
            'errors' => $errors,
            'formData' => $this->getFormData($request),
            'countries' => self::getShippingCountries($request->getLocale()),
            'customerAddresses' => $customerAddresses,
            'reservationSeconds' => $this->reservationManager->getRemainingSeconds(),
            'extensionsLeft' => $this->reservationManager->getExtensionsLeftForCurrentSession(),
            'appliedDiscounts' => $appliedDiscounts,
            'appliedCoupon' => $appliedCoupon,
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
        $locale = $request->getLocale();
        $orderRef = $request->getSession()->get('_pending_order');

        if ($orderRef === null) {
            return $this->redirectToRoute('shop_checkout', ['_locale' => $locale]);
        }

        $order = $this->orderRepository->findByReference($orderRef);

        if ($order === null) {
            $request->getSession()->remove('_pending_order');

            return $this->redirectToRoute('shop_checkout', ['_locale' => $locale]);
        }

        // The scheduler may have confirmed or cancelled the order meanwhile
        // (e.g. the customer comes back long after a 3DS redirect).
        if ($order->getStatus() !== OrderStatus::Pending) {
            if ($order->getStatus() === OrderStatus::Cancelled) {
                $request->getSession()->remove('_pending_order');
                $this->addFlash('warning', $this->translator->trans('checkout.order_cancelled_restart'));

                return $this->redirectToRoute('shop_checkout', ['_locale' => $locale]);
            }

            $this->cartManager->clear();
            $this->clearCheckoutSession($request);

            return $this->redirectToRoute(
                $locale === 'fr' ? 'shop_order_confirmation_fr' : 'shop_order_confirmation',
                ['_locale' => $locale, 'reference' => $order->getReference(), 'token' => $order->getInvoiceToken()],
            );
        }

        // Create or reuse Stripe PaymentIntent
        $paymentIntentId = $order->getStripePaymentIntentId();

        try {
            $paymentIntent = $paymentIntentId !== null
                ? $this->stripeService->retrievePaymentIntent($paymentIntentId)
                : null;

            // Availability and staleness only matter while nothing has been
            // charged yet: a succeeded intent (e.g. back from a 3DS redirect)
            // must reach confirmPayment(), whose conflict handling refunds.
            if ($paymentIntent?->status !== 'succeeded') {
                // Unique pieces: never take a payment for an item that was
                // sold or reserved by another session since the order was built.
                if (!$this->orderItemsStillAvailable($order)) {
                    $this->addFlash('warning', $this->translator->trans('checkout.items_unavailable'));

                    return $this->redirectToRoute('shop_checkout', ['_locale' => $locale]);
                }

                // A stale order (cart or coupon changed since the checkout
                // POST, reached by direct navigation) must go through checkout
                // again so its items and totals are rebuilt.
                if ($this->isOrderStale($order, $request)) {
                    $this->addFlash('warning', $this->translator->trans('checkout.order_needs_refresh'));

                    return $this->redirectToRoute('shop_checkout', ['_locale' => $locale]);
                }
            }

            if ($paymentIntent === null) {
                $paymentIntent = $this->stripeService->createPaymentIntent($order);
                $order->setStripePaymentIntentId($paymentIntent->id);
                $this->entityManager->flush();
            }
        } catch (\Exception $e) {
            $this->logger->error('Stripe PaymentIntent error: {message}', ['message' => $e->getMessage()]);
            $this->addFlash('error', $this->translator->trans('checkout.payment_error'));

            return $this->redirectToRoute('shop_checkout', ['_locale' => $locale]);
        }

        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);

        return $this->render('shop/checkout/payment.html.twig', [
            'order' => $order,
            'totalConverted' => $this->currencyConverter->convert($order->getTotalEur(), $currency),
            'currency' => $currency,
            'stripePublicKey' => $this->stripePublicKey,
            'clientSecret' => $paymentIntent->client_secret,
            'reservationSeconds' => $this->reservationManager->getRemainingSeconds(),
            'extensionsLeft' => $this->reservationManager->getExtensionsLeftForCurrentSession(),
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

        if ($orderRef === null) {
            return new JsonResponse(['error' => 'no_pending_order'], Response::HTTP_BAD_REQUEST);
        }

        $order = $this->orderRepository->findByReference($orderRef);

        if ($order === null || $order->getStripePaymentIntentId() === null) {
            return new JsonResponse(['error' => 'order_not_found'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $paymentIntent = $this->stripeService->retrievePaymentIntent($order->getStripePaymentIntentId());
        } catch (\Exception $e) {
            $this->logger->error('Stripe retrieve error: {message}', ['message' => $e->getMessage()]);

            return new JsonResponse(['error' => 'payment_verification_failed'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($paymentIntent->status !== 'succeeded') {
            return new JsonResponse(['error' => 'payment_not_completed', 'status' => $paymentIntent->status], Response::HTTP_BAD_REQUEST);
        }

        // Tripwire, not a gate: the intent is always created server-side from
        // the order total, so a mismatch can only be a bug worth loud logging.
        $expectedCents = (int) \round($order->getTotalEur() * 100);
        if ($paymentIntent->amount !== $expectedCents) {
            $this->logger->critical('PaymentIntent amount mismatch for order {ref}: charged {charged}, expected {expected}.', [
                'ref' => $order->getReference(),
                'charged' => $paymentIntent->amount,
                'expected' => $expectedCents,
            ]);
        }

        // Single confirmation path, shared with the scheduler: idempotence,
        // unique-piece conflict detection and invoice numbering all happen
        // atomically under locks inside the confirmer.
        $outcome = $this->orderConfirmer->confirm($order, $paymentIntent->status, $request->getLocale());

        switch ($outcome) {
            case OrderConfirmationOutcome::AlreadyCancelled:
                return new JsonResponse(
                    ['error' => $this->translator->trans('checkout.order_cancelled_restart')],
                    Response::HTTP_CONFLICT,
                );

            case OrderConfirmationOutcome::CancelledFullConflict:
                $this->cartManager->clear();
                $this->clearCheckoutSession($request);

                return new JsonResponse(
                    ['error' => $this->translator->trans('checkout.conflict_full_refund')],
                    Response::HTTP_CONFLICT,
                );

            case OrderConfirmationOutcome::AlreadyProcessed:
                $this->cartManager->clear();
                $this->clearCheckoutSession($request);

                return $this->confirmationRedirectResponse($order, $request->getLocale());

            case OrderConfirmationOutcome::Confirmed:
            case OrderConfirmationOutcome::ConfirmedPartialRefund:
                $this->recordSessionPromotionUsage($order, $request);
                $this->cartManager->clear();
                $this->clearCheckoutSession($request);

                return $this->confirmationRedirectResponse($order, $request->getLocale());
        }
    }

    /**
     * Track promotion usage from the shares stored in session at checkout
     * POST time (already clamped there). Session-bound, hence not part of
     * OrderConfirmer — scheduler-confirmed orders skip usage tracking.
     */
    private function recordSessionPromotionUsage(Order $order, Request $request): void
    {
        $sessionPromos = $request->getSession()->get('_order_promotions', []);

        if (!\is_array($sessionPromos) || $sessionPromos === []) {
            return;
        }

        $promoRepo = $this->entityManager->getRepository(\App\Entity\Promotion::class);
        $appliedPromotions = [];

        foreach ($sessionPromos as $entry) {
            $promo = $promoRepo->find($entry['id']);
            if ($promo !== null) {
                $appliedPromotions[] = ['promotion' => $promo, 'discount' => (float) $entry['discount']];
            }
        }

        if ($appliedPromotions !== []) {
            $this->promotionEngine->recordUsage($order, $appliedPromotions);
        }
    }

    private function confirmationRedirectResponse(Order $order, string $locale): JsonResponse
    {
        $confirmationRoute = $locale === 'fr' ? 'shop_order_confirmation_fr' : 'shop_order_confirmation';

        return new JsonResponse([
            'success' => true,
            'redirectUrl' => $this->generateUrl($confirmationRoute, [
                '_locale' => $locale,
                'reference' => $order->getReference(),
                'token' => $order->getInvoiceToken(),
            ]),
        ]);
    }

    private function clearCheckoutSession(Request $request): void
    {
        $request->getSession()->remove('_pending_order');
        $request->getSession()->remove('_checkout_email');
        $request->getSession()->remove('_order_promotions');
        $request->getSession()->remove('_promo_code');
    }

    /**
     * Whether every item of the order is still purchasable: product present,
     * not sold, and not reserved by another session.
     */
    private function orderItemsStillAvailable(Order $order): bool
    {
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();

            if ($product === null || $product->isSoldOut() || $this->reservationManager->isReservedByOther($product)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A pending order is stale when the cart content or the applied coupon
     * changed since the checkout POST that built it.
     */
    private function isOrderStale(Order $order, Request $request): bool
    {
        $cartIds = $this->cartManager->getProductIds();
        \sort($cartIds);

        $orderIds = [];
        foreach ($order->getItems() as $item) {
            $orderIds[] = $item->getProduct()?->getId();
        }
        \sort($orderIds);

        if ($cartIds !== $orderIds) {
            return true;
        }

        $sessionCode = \strtoupper(\trim((string) $request->getSession()->get('_promo_code', '')));

        return $sessionCode !== ($order->getPromotionCode() ?? '');
    }

    /**
     * Free orders are not supported: the discount is capped so the amount to
     * charge never drops below Stripe's minimum.
     */
    private function clampDiscount(float $discountEur, float $chargeableBaseEur): float
    {
        return \max(0.0, \min($discountEur, $chargeableBaseEur - StripeService::MINIMUM_CHARGE_EUR));
    }

    /**
     * Scale each promotion's recorded discount so the shares add up to the
     * clamped total (the last share absorbs rounding): these shares feed the
     * PDF invoice lines and PromotionUsage, which must match what is charged.
     *
     * @param list<array{promotion: \App\Entity\Promotion, discount: float}> $promotions
     *
     * @return list<array{promotion: \App\Entity\Promotion, discount: float}>
     */
    private function scalePromotionShares(array $promotions, float $rawTotal, float $clampedTotal): array
    {
        if ($promotions === [] || $rawTotal <= 0.0 || $rawTotal === $clampedTotal) {
            return $promotions;
        }

        $factor = $clampedTotal / $rawTotal;
        $shares = [];
        $allocated = 0.0;
        $lastIndex = \count($promotions) - 1;

        foreach ($promotions as $index => $entry) {
            $share = $index === $lastIndex
                ? \round($clampedTotal - $allocated, 2)
                : \round((float) $entry['discount'] * $factor, 2);
            $allocated += $share;
            $shares[] = ['promotion' => $entry['promotion'], 'discount' => $share];
        }

        return $shares;
    }

    #[Route(
        path: '/order/{reference}/confirmation/{token}',
        name: 'shop_order_confirmation',
        methods: ['GET'],
        requirements: ['_locale' => 'en'],
    )]
    #[Route(
        path: '/commande/{reference}/confirmation/{token}',
        name: 'shop_order_confirmation_fr',
        methods: ['GET'],
        requirements: ['_locale' => 'fr'],
    )]
    public function confirmation(string $reference, string $token, Request $request): Response
    {
        $order = $this->orderRepository->findByReference($reference);

        // References are sequential, hence guessable: the invoice token is
        // required so order details never leak through enumeration.
        if ($order === null || !\hash_equals($order->getInvoiceToken(), $token)) {
            throw $this->createNotFoundException();
        }

        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);

        return $this->render('shop/checkout/confirmation.html.twig', [
            'order' => $order,
            'totalConverted' => $this->currencyConverter->convert($order->getTotalEur(), $currency),
            'currency' => $currency,
        ]);
    }

    #[Route(
        path: '/order/{reference}/tracking/{token}',
        name: 'shop_order_tracking',
        methods: ['GET'],
        requirements: ['_locale' => 'en'],
    )]
    #[Route(
        path: '/commande/{reference}/suivi/{token}',
        name: 'shop_order_tracking_fr',
        methods: ['GET'],
        requirements: ['_locale' => 'fr'],
    )]
    public function tracking(string $reference, string $token, Request $request): Response
    {
        $order = $this->orderRepository->findByReference($reference);

        // Same enumeration guard as confirmation().
        if ($order === null || !\hash_equals($order->getInvoiceToken(), $token)) {
            throw $this->createNotFoundException();
        }

        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);

        return $this->render('shop/checkout/tracking.html.twig', [
            'order' => $order,
            'totalConverted' => $this->currencyConverter->convert($order->getTotalEur(), $currency),
            'currency' => $currency,
        ]);
    }

    #[Route(
        path: '/checkout/email-check',
        name: 'shop_checkout_email_check',
        methods: ['POST'],
    )]
    public function emailCheck(Request $request, CustomerRepository $customerRepository, RateLimiterFactoryInterface $emailCheckLimiter): JsonResponse
    {
        // Guessable yes/no answers: throttle account enumeration by IP.
        $limiter = $emailCheckLimiter->create($request->getClientIp() ?? 'anonymous');

        if (!$limiter->consume()->isAccepted()) {
            return new JsonResponse(['exists' => false], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $email = \trim((string) $request->getPayload()->get('email', ''));

        if ($email === '' || \filter_var($email, \FILTER_VALIDATE_EMAIL) === false) {
            return new JsonResponse(['exists' => false]);
        }

        return new JsonResponse(['exists' => $customerRepository->findByEmail($email) !== null]);
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

        if ($email === '' || \filter_var($email, \FILTER_VALIDATE_EMAIL) === false) {
            $errors['customer_email'] = 'checkout.error.email_invalid';
        }

        if ($shippingRecipient === '') {
            $errors['shipping_recipient_name'] = 'checkout.error.shipping_recipient_required';
        }

        if ($line1 === '') {
            $errors['address_line1'] = 'checkout.error.address_required';
        }

        if ($city === '') {
            $errors['city'] = 'checkout.error.city_required';
        }

        if ($postalCode === '') {
            $errors['postal_code'] = 'checkout.error.postal_code_required';
        }

        if ($country === '' || !Countries::exists($country)) {
            $errors['country'] = 'checkout.error.country_required';
        }

        // Validate billing address if different from shipping
        if ($request->request->has('billing_different')) {
            $billingRecipient = \trim((string) $request->request->get('billing_recipient_name', ''));
            $billingLine1 = \trim((string) $request->request->get('billing_address_line1', ''));
            $billingCity = \trim((string) $request->request->get('billing_city', ''));
            $billingPostalCode = \trim((string) $request->request->get('billing_postal_code', ''));
            $billingCountry = \trim((string) $request->request->get('billing_country', ''));

            if ($billingRecipient === '') {
                $errors['billing_recipient_name'] = 'checkout.error.billing_recipient_required';
            }

            if ($billingLine1 === '') {
                $errors['billing_address_line1'] = 'checkout.error.billing_address_required';
            }

            if ($billingCity === '') {
                $errors['billing_city'] = 'checkout.error.billing_city_required';
            }

            if ($billingPostalCode === '') {
                $errors['billing_postal_code'] = 'checkout.error.billing_postal_code_required';
            }

            if ($billingCountry === '' || !Countries::exists($billingCountry)) {
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
    private function updateExistingOrder(Order $order, Request $request, array $products, float $subtotalEur): Order
    {
        $this->fillOrderFromRequest($order, $request);

        // Replace items if cart changed
        $oldTotal = $order->getTotalEur();
        $order->setTotalEur($subtotalEur);

        foreach ($order->getItems()->toArray() as $oldItem) {
            $order->removeItem($oldItem);
        }

        foreach ($products as $product) {
            $shippingCost = $this->shippingCostProvider->getCost($product->getShippingTier());
            $item = OrderItem::fromProduct($product, $shippingCost);
            $discountedPrice = $this->promotionEngine->getDiscountedDisplayPrice($product);
            if ($discountedPrice !== null) {
                $item->setDiscountAmountEur($this->shippingCostProvider->getDisplayPrice($product->getBasePrice(), $product->getShippingTier()) - $discountedPrice);
            }
            $order->addItem($item);
        }

        // Reset Stripe PaymentIntent if total changed (new PI will be created
        // at payment step). Cancel the obsolete intent at Stripe first: its
        // client_secret would stay payable in an open tab, capturing money
        // that no order references any more.
        if ($oldTotal !== $subtotalEur && $order->getStripePaymentIntentId() !== null) {
            try {
                $this->stripeService->cancelPaymentIntent($order->getStripePaymentIntentId());
            } catch (\Exception $e) {
                $this->logger->error('Failed to cancel obsolete PaymentIntent for order {ref} — check it in the Stripe dashboard: {message}', [
                    'ref' => $order->getReference(),
                    'message' => $e->getMessage(),
                ]);
            }

            $order->setStripePaymentIntentId(null);
        }

        return $order;
    }

    /**
     * @param Product[] $products
     */
    private function createOrder(Request $request, array $products, float $subtotalEur): Order
    {
        // The reference is assigned at flush time, under lock (see checkout()).
        $order = new Order();
        $this->fillOrderFromRequest($order, $request);
        $order->setTotalEur($subtotalEur);

        foreach ($products as $product) {
            $shippingCost = $this->shippingCostProvider->getCost($product->getShippingTier());
            $item = OrderItem::fromProduct($product, $shippingCost);
            $discountedPrice = $this->promotionEngine->getDiscountedDisplayPrice($product);
            if ($discountedPrice !== null) {
                $item->setDiscountAmountEur($this->shippingCostProvider->getDisplayPrice($product->getBasePrice(), $product->getShippingTier()) - $discountedPrice);
            }
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

        if ($count === 0) {
            return 'Home';
        }

        return 'Address '.($count + 1);
    }

    /**
     * Included zone: the 27 EU member states, the United Kingdom and
     * Switzerland — shipping is baked into display prices for these
     * destinations. Everything ships from France; metropolitan France only —
     * a DOM-TOM postal code (97x/98x) under the FR country code is treated as
     * out of the zone by computeShippingSurchargeEur() and pays the surcharge.
     */
    private const INCLUDED_ZONE_COUNTRY_CODES = [
        'AT', 'BE', 'BG', 'CH', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES',
        'FI', 'FR', 'GB', 'GR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU',
        'LV', 'MT', 'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK',
    ];

    /**
     * Shipping is worldwide: any other destination (DOM-TOM included) is
     * charged each cart item's shipping tier a second time, as a visible
     * "international shipping" line — display prices already include one
     * zone-level shipping share.
     */

    /**
     * @return array<string, string>
     */
    private static function getShippingCountries(string $locale): array
    {
        $countries = Countries::getNames($locale);

        \asort($countries, \SORT_LOCALE_STRING);

        return $countries;
    }

    /**
     * The out-of-zone shipping surcharge for a destination: the sum of every
     * cart item's shipping tier, or zero inside the included zone. Metropolitan
     * France only — a DOM-TOM postal code under the FR country code is out of
     * the included zone.
     *
     * @param Product[] $products
     */
    private function computeShippingSurchargeEur(string $country, string $postalCode, array $products): float
    {
        $insideIncludedZone = \in_array($country, self::INCLUDED_ZONE_COUNTRY_CODES, true)
            && !($country === 'FR' && \preg_match('/^\s*9[78]/', $postalCode) === 1);

        return $insideIncludedZone ? 0.0 : $this->sumShippingCosts($products);
    }

    /**
     * @param Product[] $products
     */
    private function sumShippingCosts(array $products): float
    {
        $total = 0.0;
        foreach ($products as $product) {
            $total += $this->shippingCostProvider->getCost($product->getShippingTier());
        }

        return $total;
    }

    #[Route(
        path: '/coupon/validate',
        name: 'shop_coupon_validate',
        methods: ['POST'],
    )]
    public function validateCoupon(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('submit', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['valid' => false, 'message' => 'Invalid request.'], Response::HTTP_FORBIDDEN);
        }

        $data = \json_decode($request->getContent(), true);
        $code = \strtoupper(\trim((string) ($data['code'] ?? '')));

        if ($code === '') {
            return $this->json(['valid' => false, 'message' => $this->translator->trans('checkout.coupon_invalid')]);
        }

        $products = $this->cartManager->getProducts();
        $effectiveSubtotalEur = $this->promotionEngine->getEffectiveSubtotalEur($products);
        $email = $this->getCheckoutEmail($request);

        $promo = $this->promotionEngine->validateCouponCode($code, $effectiveSubtotalEur, $email);

        if ($promo === null) {
            return $this->json(['valid' => false, 'message' => $this->translator->trans('checkout.coupon_invalid')]);
        }

        // Store validated code in session
        $request->getSession()->set('_promo_code', $code);

        return $this->json([
            'valid' => true,
            'message' => $this->translator->trans('checkout.coupon_applied'),
            'label' => $promo->getDiscountLabel(),
        ]);
    }

    #[Route(
        path: '/coupon/remove',
        name: 'shop_coupon_remove',
        methods: ['POST'],
    )]
    public function removeCoupon(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('submit', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['removed' => false], Response::HTTP_FORBIDDEN);
        }

        $request->getSession()->remove('_promo_code');

        return $this->json(['removed' => true]);
    }

    #[Route(
        path: '/checkout/extend-reservation',
        name: 'shop_checkout_extend_reservation',
        methods: ['POST'],
    )]
    public function extendReservation(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('submit', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['extended' => false], Response::HTTP_FORBIDDEN);
        }

        $result = $this->reservationManager->extendForCurrentSession();

        if ($result === null) {
            return $this->json(['extended' => false, 'remainingSeconds' => 0, 'extensionsLeft' => 0]);
        }

        return $this->json([
            'extended' => true,
            'remainingSeconds' => $result['remainingSeconds'],
            'extensionsLeft' => $result['extensionsLeft'],
        ]);
    }

    private function getCheckoutEmail(Request $request): ?string
    {
        $user = $this->getUser();

        if ($user instanceof Customer) {
            return $user->getEmail();
        }

        return $request->getSession()->get('_checkout_email');
    }

    private function flashRemovedItemsWarning(Request $request): void
    {
        $removed = $request->getSession()->get('_cart_items_removed', 0);

        if ($removed > 0) {
            $request->getSession()->remove('_cart_items_removed');
            $this->addFlash('warning', $this->translator->trans('cart.items_removed_warning', ['%count%' => $removed]));
        }
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
