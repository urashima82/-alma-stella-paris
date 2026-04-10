<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ShippingSettings;
use App\Enum\ShippingTier;
use App\Repository\ShippingSettingsRepository;

class ShippingCostProvider
{
    /** @var array<string, ShippingSettings>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly ShippingSettingsRepository $repository,
    ) {
    }

    public function getCost(ShippingTier $tier): float
    {
        $settings = $this->getSettings($tier);

        return $settings?->getShippingCostUsd() ?? $tier->shippingCostUsd();
    }

    public function getLabel(ShippingTier $tier): string
    {
        $settings = $this->getSettings($tier);

        return $settings?->getLabel() ?? $tier->label();
    }

    public function getMaxWeight(ShippingTier $tier): int
    {
        $settings = $this->getSettings($tier);

        return $settings?->getMaxWeightGrams() ?? $tier->maxWeightGrams();
    }

    public function getDisplayPrice(float $basePrice, ShippingTier $tier): float
    {
        return $basePrice + $this->getCost($tier);
    }

    private function getSettings(ShippingTier $tier): ?ShippingSettings
    {
        if (null === $this->cache) {
            $this->cache = $this->repository->findAllIndexedByTier();
        }

        return $this->cache[$tier->value] ?? null;
    }
}
