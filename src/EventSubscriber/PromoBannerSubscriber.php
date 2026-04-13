<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\PromotionEngine;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class PromoBannerSubscriber implements EventSubscriberInterface
{
    private const SESSION_KEY = '_promo_code';
    private const COOKIE_NAME = 'promo_code';
    private const COOKIE_LIFETIME_DAYS = 7;
    private const QUERY_PARAM = 'promo';

    public function __construct(
        private readonly PromotionEngine $promotionEngine,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 15],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Check for ?promo=CODE query parameter
        $promoCode = $request->query->get(self::QUERY_PARAM);

        if (null !== $promoCode && '' !== $promoCode) {
            $promoCode = \strtoupper(\trim($promoCode));

            // Validate the code exists and is valid
            $promotion = $this->promotionEngine->validateCouponCode($promoCode);

            if (null !== $promotion) {
                $request->getSession()->set(self::SESSION_KEY, $promoCode);
                $request->getSession()->set('_promo_banner_label', $promotion->getDiscountLabel());
                $request->getSession()->set('_promo_banner_name', $promotion->getName());

                // Store in cookie for persistence
                $request->attributes->set('_promo_cookie', Cookie::create(self::COOKIE_NAME)
                    ->withValue($promoCode)
                    ->withExpires(new \DateTimeImmutable(\sprintf('+%d days', self::COOKIE_LIFETIME_DAYS)))
                    ->withPath('/')
                    ->withSameSite('lax'));
            }

            return;
        }

        // Re-validate code already in session (admin may have deactivated/expired it)
        $sessionCode = $request->getSession()->get(self::SESSION_KEY);

        if (null !== $sessionCode && '' !== $sessionCode) {
            $promotion = $this->promotionEngine->validateCouponCode($sessionCode);

            if (null === $promotion) {
                // Code no longer valid — clear session and cookie
                $request->getSession()->remove(self::SESSION_KEY);
                $request->getSession()->remove('_promo_banner_label');
                $request->getSession()->remove('_promo_banner_name');
                $request->attributes->set('_promo_cookie_clear', true);
            }

            return;
        }

        // Restore from cookie if not in session
        $cookieCode = $request->cookies->get(self::COOKIE_NAME);

        if (null !== $cookieCode && '' !== $cookieCode) {
            $cookieCode = \strtoupper(\trim($cookieCode));
            $promotion = $this->promotionEngine->validateCouponCode($cookieCode);

            if (null !== $promotion) {
                $request->getSession()->set(self::SESSION_KEY, $cookieCode);
                $request->getSession()->set('_promo_banner_label', $promotion->getDiscountLabel());
                $request->getSession()->set('_promo_banner_name', $promotion->getName());
            } else {
                // Code expired/invalid — clear cookie
                $request->attributes->set('_promo_cookie_clear', true);
            }
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Set promo cookie if requested
        $cookie = $request->attributes->get('_promo_cookie');
        if ($cookie instanceof Cookie) {
            $response->headers->setCookie($cookie);
        }

        // Clear promo cookie if requested
        if ($request->attributes->get('_promo_cookie_clear')) {
            $response->headers->clearCookie(self::COOKIE_NAME, '/');
        }
    }
}
