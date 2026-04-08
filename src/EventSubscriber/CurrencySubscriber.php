<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\CurrencyConverter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CurrencySubscriber implements EventSubscriberInterface
{
    private const COOKIE_NAME = 'currency';
    private const COOKIE_LIFETIME_DAYS = 30;
    private const SESSION_KEY = '_currency';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 20],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * Restore currency from cookie into session on first request.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ($request->getSession()->has(self::SESSION_KEY)) {
            return;
        }

        $cookieCurrency = $request->cookies->get(self::COOKIE_NAME);

        if (null !== $cookieCurrency && CurrencyConverter::isSupported($cookieCurrency)) {
            $request->getSession()->set(self::SESSION_KEY, \strtoupper($cookieCurrency));
        }
    }

    /**
     * Persist currency selection to cookie on every response.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $currency = $request->getSession()->get(self::SESSION_KEY, CurrencyConverter::BASE_CURRENCY);

        $currentCookie = $request->cookies->get(self::COOKIE_NAME);
        if ($currentCookie !== $currency) {
            $event->getResponse()->headers->setCookie(
                Cookie::create(self::COOKIE_NAME)
                    ->withValue($currency)
                    ->withExpires(new \DateTimeImmutable(\sprintf('+%d days', self::COOKIE_LIFETIME_DAYS)))
                    ->withPath('/')
                    ->withSameSite('lax')
            );
        }
    }
}
