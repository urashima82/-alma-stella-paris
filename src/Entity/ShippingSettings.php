<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ShippingTier;
use App\Repository\ShippingSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShippingSettingsRepository::class)]
class ShippingSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true, enumType: ShippingTier::class)]
    private ShippingTier $tier;

    #[ORM\Column(length: 100)]
    private string $label;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $shippingCostEur = '0.00';

    #[ORM\Column]
    private int $maxWeightGrams = 0;

    public function __toString(): string
    {
        return $this->label;
    }

    public static function createFromTier(ShippingTier $tier): self
    {
        $settings = new self();
        $settings->tier = $tier;
        $settings->label = $tier->label();
        $settings->shippingCostEur = \number_format($tier->shippingCostEur(), 2, '.', '');
        $settings->maxWeightGrams = $tier->maxWeightGrams();

        return $settings;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTier(): ShippingTier
    {
        return $this->tier;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getShippingCostEur(): float
    {
        return (float) $this->shippingCostEur;
    }

    public function setShippingCostEur(float $shippingCostEur): static
    {
        $this->shippingCostEur = \number_format($shippingCostEur, 2, '.', '');

        return $this;
    }

    public function getMaxWeightGrams(): int
    {
        return $this->maxWeightGrams;
    }

    public function setMaxWeightGrams(int $maxWeightGrams): static
    {
        $this->maxWeightGrams = $maxWeightGrams;

        return $this;
    }
}
