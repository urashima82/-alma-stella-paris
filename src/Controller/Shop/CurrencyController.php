<?php

declare(strict_types=1);

namespace App\Controller\Shop;

use App\Service\CurrencyConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CurrencyController extends AbstractController
{
    private function isSafeRedirectUrl(string $url, Request $request): bool
    {
        $parsed = \parse_url($url);

        if ($parsed === false || !isset($parsed['host'])) {
            // Relative URL — safe
            return true;
        }

        return $parsed['host'] === $request->getHost();
    }

    #[Route('/currency/switch', name: 'currency_switch', methods: ['POST'])]
    public function switch(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('submit', $request->request->get('_token'))) {
            return $this->redirectToRoute('shop_home', ['_locale' => $request->getLocale()]);
        }

        $currency = \strtoupper((string) $request->request->get('currency', CurrencyConverter::BASE_CURRENCY));

        if (!CurrencyConverter::isSupported($currency)) {
            $currency = CurrencyConverter::BASE_CURRENCY;
        }

        $request->getSession()->set('_currency', $currency);

        $referer = $request->headers->get('referer');

        if ($referer !== null && $this->isSafeRedirectUrl($referer, $request)) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('shop_home', ['_locale' => $request->getLocale()]);
    }
}
