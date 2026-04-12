<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Applies pending cart cookies (set via request attributes) to the HTTP response.
 */
class CartCookieSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        /** @var Cookie[] $cookies */
        $cookies = $event->getRequest()->attributes->get('_pending_cookies', []);

        foreach ($cookies as $cookie) {
            $event->getResponse()->headers->setCookie($cookie);
        }
    }
}
