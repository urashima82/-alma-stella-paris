<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class LocaleRedirectController extends AbstractController
{
    private const SUPPORTED_LOCALES = ['en', 'fr'];
    private const DEFAULT_LOCALE = 'en';
    private const COOKIE_NAME = 'locale';

    #[Route('/', name: 'root_redirect')]
    public function index(Request $request): Response
    {
        $locale = $this->detectLocale($request);

        return $this->redirectToRoute('shop_home', ['_locale' => $locale]);
    }

    private function detectLocale(Request $request): string
    {
        $cookieLocale = $request->cookies->get(self::COOKIE_NAME);
        if ($cookieLocale !== null && \in_array($cookieLocale, self::SUPPORTED_LOCALES, true)) {
            return $cookieLocale;
        }

        $preferredLocale = $request->getPreferredLanguage(self::SUPPORTED_LOCALES);
        if ($preferredLocale !== null) {
            return $preferredLocale;
        }

        return self::DEFAULT_LOCALE;
    }
}
