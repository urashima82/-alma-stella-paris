<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CurrencyConverter
{
    public const string BASE_CURRENCY = 'EUR';
    public const array SUPPORTED_CURRENCIES = ['EUR', 'USD', 'CAD', 'GBP', 'MXN'];
    private const string API_URL = 'https://open.er-api.com/v6/latest/EUR';
    private const int CACHE_TTL_SECONDS = 21600; // 6 hours

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Convert an amount from EUR to the target currency.
     */
    public function convert(float $amountEur, string $targetCurrency): float
    {
        $targetCurrency = \strtoupper($targetCurrency);

        if ($targetCurrency === self::BASE_CURRENCY) {
            return $amountEur;
        }

        $rate = $this->getRate($targetCurrency);

        return \round($amountEur * $rate, 2);
    }

    /**
     * Get the exchange rate for a given currency relative to EUR.
     * Returns 1.0 as fallback if the API is unavailable or the currency is unsupported.
     */
    public function getRate(string $currency): float
    {
        $currency = \strtoupper($currency);

        if ($currency === self::BASE_CURRENCY) {
            return 1.0;
        }

        $rates = $this->fetchRates();

        return $rates[$currency] ?? 1.0;
    }

    /**
     * Get the currency symbol for display.
     */
    public static function getSymbol(string $currency): string
    {
        return match (\strtoupper($currency)) {
            'USD' => '$',
            'EUR' => "\u{20AC}",
            'CAD' => 'CA$',
            'GBP' => "\u{00A3}",
            'MXN' => 'MX$',
            default => "\u{20AC}",
        };
    }

    /**
     * Check if a currency code is supported.
     */
    public static function isSupported(string $currency): bool
    {
        return \in_array(\strtoupper($currency), self::SUPPORTED_CURRENCIES, true);
    }

    /**
     * @return array<string, float>
     */
    private function fetchRates(): array
    {
        return $this->cache->get('currency_exchange_rates', function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            try {
                $response = $this->httpClient->request('GET', self::API_URL);
                $data = $response->toArray();

                if ('success' !== ($data['result'] ?? null)) {
                    return [];
                }

                $allRates = $data['rates'] ?? [];
                $filtered = [];

                foreach (self::SUPPORTED_CURRENCIES as $code) {
                    if (isset($allRates[$code])) {
                        $filtered[$code] = (float) $allRates[$code];
                    }
                }

                return $filtered;
            } catch (\Throwable) {
                // Fallback: return empty array, getRate() will return 1.0
                $item->expiresAfter(300); // Retry in 5 minutes on failure

                return [];
            }
        });
    }
}
