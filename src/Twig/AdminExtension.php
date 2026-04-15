<?php

declare(strict_types=1);

namespace App\Twig;

use App\Repository\SiteSettingsRepository;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class AdminExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly SiteSettingsRepository $siteSettingsRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        $settings = $this->siteSettingsRepository->findOneBy([]);

        return [
            'admin_maintenance_mode' => $settings?->isMaintenanceMode() ?? false,
        ];
    }
}
