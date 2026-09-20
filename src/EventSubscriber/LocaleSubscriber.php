<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private const COOKIE_NAME = 'locale';
    private const COOKIE_LIFETIME_DAYS = 30;
    private const SUPPORTED_LOCALES = ['en', 'fr'];

    /** Mirrors the `admin` firewall pattern in `config/packages/security.yaml`. */
    private const ADMIN_PATH_PREFIX = '/admin';

    /** The back-office is French by decision — see CLAUDE.md. */
    private const ADMIN_LOCALE = 'fr';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    /**
     * Detect locale from URL prefix, session, or cookie — even when no route matches (e.g. 404 pages).
     * Runs at high priority so the locale is set before any rendering.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if ($request->attributes->has('_locale')) {
            return;
        }

        $path = $request->getPathInfo();

        // Back-office URLs carry no locale prefix, so without this they fell
        // through to the visitor's shop cookie — which is why EasyAdmin's own
        // labels ("Save changes", "Edit", "Delete") rendered in English
        // whenever that cookie said `en`, or was simply absent.
        if ($this->isAdminPath($path)) {
            $request->setLocale(self::ADMIN_LOCALE);

            return;
        }

        if (\preg_match('#^/(en|fr)(/|$)#', $path, $matches)) {
            $request->setLocale($matches[1]);

            return;
        }

        $sessionLocale = $request->hasPreviousSession()
            ? $request->getSession()->get('_locale')
            : null;

        if ($sessionLocale !== null && \in_array($sessionLocale, self::SUPPORTED_LOCALES, true)) {
            $request->setLocale($sessionLocale);

            return;
        }

        $cookieLocale = $request->cookies->get(self::COOKIE_NAME);
        if ($cookieLocale !== null && \in_array($cookieLocale, self::SUPPORTED_LOCALES, true)) {
            $request->setLocale($cookieLocale);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // The session and cookie below hold the visitor's *storefront* choice.
        // An admin page runs on a forced locale, so persisting it here would
        // silently flip the shop to French behind the back office.
        if ($this->isAdminPath($request->getPathInfo())) {
            return;
        }

        $locale = $request->getLocale();

        $request->getSession()->set('_locale', $locale);

        $currentCookie = $request->cookies->get(self::COOKIE_NAME);
        if ($currentCookie !== $locale) {
            $event->getResponse()->headers->setCookie(
                Cookie::create(self::COOKIE_NAME)
                    ->withValue($locale)
                    ->withExpires(new \DateTimeImmutable(\sprintf('+%d days', self::COOKIE_LIFETIME_DAYS)))
                    ->withPath('/')
                    ->withSameSite('lax')
            );
        }
    }

    private function isAdminPath(string $path): bool
    {
        return $path === self::ADMIN_PATH_PREFIX
            || \str_starts_with($path, self::ADMIN_PATH_PREFIX.'/');
    }
}
