<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $appEnv,
    ) {
    }

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

        $response = $event->getResponse();
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($this->appEnv === 'prod') {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Content-Security-Policy: allow inline styles/scripts (Tailwind, importmap,
        // JSON-LD structured data), Google Fonts, Stripe.js, Behold (Instagram feed),
        // and Cloudflare Turnstile (bot protection)
        $csp = "default-src 'self'; "
            ."script-src 'self' 'unsafe-inline' data: https://js.stripe.com https://feeds.behold.so https://cdn.jsdelivr.net https://challenges.cloudflare.com; "
            ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
            ."font-src 'self' https://fonts.gstatic.com; "
            ."img-src 'self' data: https://*.cdninstagram.com https://*.behold.so https://behold.pictures; "
            .'frame-src https://js.stripe.com https://challenges.cloudflare.com; '
            ."connect-src 'self' https://api.stripe.com https://feeds.behold.so;";

        $headers->set('Content-Security-Policy', $csp);
    }
}
