<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriber implements EventSubscriberInterface
{
    private const COOKIE_NAME = 'locale';
    private const COOKIE_LIFETIME_DAYS = 30;

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
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
}
