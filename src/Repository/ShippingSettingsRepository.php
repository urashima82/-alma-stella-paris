<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShippingSettings;
use App\Enum\ShippingTier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShippingSettings>
 */
class ShippingSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShippingSettings::class);
    }

    public function findByTier(ShippingTier $tier): ?ShippingSettings
    {
        return $this->findOneBy(['tier' => $tier]);
    }

    /**
     * @return array<string, ShippingSettings>
     */
    public function findAllIndexedByTier(): array
    {
        $settings = $this->findAll();
        $indexed = [];

        foreach ($settings as $setting) {
            $indexed[$setting->getTier()->value] = $setting;
        }

        return $indexed;
    }
}
