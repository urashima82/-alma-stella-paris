<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TurnstileExtension extends AbstractExtension
{
    public function __construct(
        private readonly string $turnstileSiteKey,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('turnstile_site_key', $this->getSiteKey(...)),
            new TwigFunction('turnstile_enabled', $this->isEnabled(...)),
        ];
    }

    public function getSiteKey(): string
    {
        return $this->turnstileSiteKey;
    }

    public function isEnabled(): bool
    {
        return $this->turnstileSiteKey !== '';
    }
}
