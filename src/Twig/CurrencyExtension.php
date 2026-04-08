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
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('current_currency', $this->getCurrentCurrency(...)),
            new TwigFunction('is_non_usd_currency', $this->isNonUsdCurrency(...)),
            new TwigFunction('currency_symbol', [CurrencyConverter::class, 'getSymbol']),
        ];
    }

    /**
     * Format a USD price into the visitor's selected currency.
     */
    public function formatPrice(float $amountUsd, ?string $currency = null): string
    {
        $currency = $currency ?? $this->getCurrentCurrency();
        $converted = $this->currencyConverter->convert($amountUsd, $currency);
        $symbol = CurrencyConverter::getSymbol($currency);

        // Format with 2 decimals, strip trailing .00
        $formatted = \number_format($converted, 2, '.', ',');

        if (\str_ends_with($formatted, '.00')) {
            $formatted = \substr($formatted, 0, -3);
        }

        return $symbol.$formatted;
    }

    public function getCurrentCurrency(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return CurrencyConverter::BASE_CURRENCY;
        }

        $currency = $request->getSession()->get('_currency', CurrencyConverter::BASE_CURRENCY);

        if (!CurrencyConverter::isSupported($currency)) {
            return CurrencyConverter::BASE_CURRENCY;
        }

        return $currency;
    }

    public function isNonUsdCurrency(): bool
    {
        return CurrencyConverter::BASE_CURRENCY !== $this->getCurrentCurrency();
    }
}
