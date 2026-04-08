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
    #[Route('/currency/switch', name: 'currency_switch', methods: ['POST'])]
    public function switch(Request $request): Response
    {
        $currency = \strtoupper((string) $request->request->get('currency', CurrencyConverter::BASE_CURRENCY));

        if (!CurrencyConverter::isSupported($currency)) {
            $currency = CurrencyConverter::BASE_CURRENCY;
        }

        $request->getSession()->set('_currency', $currency);

        $referer = $request->headers->get('referer');

        if (null !== $referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('shop_home', ['_locale' => $request->getLocale()]);
    }
}
