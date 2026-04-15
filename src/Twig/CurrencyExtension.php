<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\CurrencyConverter;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class CurrencyExtension extends AbstractExtension
{
    public function __construct(
        private readonly CurrencyConverter $currencyConverter,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('price', $this->formatPrice(...)),
            new TwigFilter('price_format', $this->formatPriceRaw(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('current_currency', $this->getCurrentCurrency(...)),
            new TwigFunction('is_non_eur_currency', $this->isNonEurCurrency(...)),
            new TwigFunction('currency_symbol', [CurrencyConverter::class, 'getSymbol']),
        ];
    }

    /**
     * Format a EUR price into the visitor's selected currency.
     */
    public function formatPrice(float $amountEur, ?string $currency = null): string
    {
        $currency = $currency ?? $this->getCurrentCurrency();
        $converted = $this->currencyConverter->convert($amountEur, $currency);
        $symbol = CurrencyConverter::getSymbol($currency);

        // Format with 2 decimals, strip trailing .00
        $formatted = \number_format($converted, 2, '.', ',');

        if (\str_ends_with($formatted, '.00')) {
            $formatted = \substr($formatted, 0, -3);
        }

        return $symbol.$formatted;
    }

    /**
     * Format an already-converted amount with the currency symbol (no conversion).
     */
    public function formatPriceRaw(float $amount, ?string $currency = null): string
    {
        $currency = $currency ?? $this->getCurrentCurrency();
        $symbol = CurrencyConverter::getSymbol($currency);

        $formatted = \number_format($amount, 2, '.', ',');

        if (\str_ends_with($formatted, '.00')) {
            $formatted = \substr($formatted, 0, -3);
        }

        return $symbol.$formatted;
    }

    public function getCurrentCurrency(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if ($request === null) {
            return CurrencyConverter::BASE_CURRENCY;
        }

        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);

        if (!CurrencyConverter::isSupported($currency)) {
            return CurrencyConverter::BASE_CURRENCY;
        }

        return $currency;
    }

    public function isNonEurCurrency(): bool
    {
        return $this->getCurrentCurrency() !== CurrencyConverter::BASE_CURRENCY;
    }
}
