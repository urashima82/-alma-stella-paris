<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TrackingExtension extends AbstractExtension
{
    private const TRACKING_URL = 'https://t.17track.net/en#nums=%s';

    public function getFunctions(): array
    {
        return [
            new TwigFunction('tracking_url', $this->getTrackingUrl(...)),
        ];
    }

    public function getTrackingUrl(?string $trackingNumber): ?string
    {
        if (null === $trackingNumber || '' === $trackingNumber) {
            return null;
        }

        return \sprintf(self::TRACKING_URL, \urlencode($trackingNumber));
    }
}
