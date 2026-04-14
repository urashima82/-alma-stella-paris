<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SiteSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteSettings>
 */
class SiteSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteSettings::class);
    }

    public function getSettings(): SiteSettings
    {
        return $this->findOneBy([]) ?? new SiteSettings();
    }

    public function getActiveCollection(): string
    {
        return $this->getSettings()->getActiveCollection();
    }
}
