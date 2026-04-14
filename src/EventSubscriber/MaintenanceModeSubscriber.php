<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Repository\SiteSettingsRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

class MaintenanceModeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SiteSettingsRepository $siteSettingsRepository,
        private readonly Environment $twig,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 64],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Always allow admin routes and profiler
        if (\str_starts_with($path, '/admin') || \str_starts_with($path, '/_')) {
            return;
        }

        $settings = $this->siteSettingsRepository->getSettings();

        if (!$settings->isMaintenanceMode()) {
            return;
        }

        // Allow whitelisted IPs
        $clientIp = $request->getClientIp();
        if ($clientIp !== null && \in_array($clientIp, $settings->getMaintenanceAllowedIps(), true)) {
            return;
        }

        $locale = $this->resolveLocale($request);

        $html = $this->twig->render('shop/maintenance.html.twig', [
            'locale' => $locale,
            'message' => $locale === 'fr'
                ? $settings->getMaintenanceMessage()
                : $settings->getMaintenanceMessageEn(),
        ]);

        $event->setResponse(new Response($html, Response::HTTP_SERVICE_UNAVAILABLE));
    }

    private function resolveLocale(Request $request): string
    {
        // 1. URL prefix (e.g. /fr/... or /en/...) — most reliable source
        $path = $request->getPathInfo();
        if (\preg_match('#^/(fr|en)(/|$)#', $path, $matches)) {
            return $matches[1];
        }

        // 2. Cookie set by LocaleSubscriber
        $cookieLocale = $request->cookies->get('locale');
        if ($cookieLocale === 'fr' || $cookieLocale === 'en') {
            return $cookieLocale;
        }

        // 3. Accept-Language header
        $preferred = $request->getPreferredLanguage(['fr', 'en']);

        return $preferred ?? 'en';
    }
}
