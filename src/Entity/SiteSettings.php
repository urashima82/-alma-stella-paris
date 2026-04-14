<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SiteSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SiteSettingsRepository::class)]
class SiteSettings
{
    public const string COLLECTION_FRANCE = 'france';
    public const string COLLECTION_MEXICO = 'mexico';
    public const string COLLECTION_ALL = 'all';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private string $activeCollection = self::COLLECTION_ALL;

    #[ORM\Column]
    private bool $isMaintenanceMode = false;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $maintenanceMessage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $maintenanceMessageEn = null;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $maintenanceAllowedIps = [];

    public function __toString(): string
    {
        return 'Paramètres du site';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActiveCollection(): string
    {
        return $this->activeCollection;
    }

    public function setActiveCollection(string $activeCollection): static
    {
        $this->activeCollection = $activeCollection;

        return $this;
    }

    public function getActiveCollectionLabel(): string
    {
        return match ($this->activeCollection) {
            self::COLLECTION_FRANCE => 'Collection France',
            self::COLLECTION_MEXICO => 'Collection Mexique',
            default => 'Toutes les collections',
        };
    }

    public function isMaintenanceMode(): bool
    {
        return $this->isMaintenanceMode;
    }

    public function setIsMaintenanceMode(bool $isMaintenanceMode): static
    {
        $this->isMaintenanceMode = $isMaintenanceMode;

        return $this;
    }

    public function getMaintenanceMessage(): ?string
    {
        return $this->maintenanceMessage;
    }

    public function setMaintenanceMessage(?string $maintenanceMessage): static
    {
        $this->maintenanceMessage = $maintenanceMessage;

        return $this;
    }

    public function getMaintenanceMessageEn(): ?string
    {
        return $this->maintenanceMessageEn;
    }

    public function setMaintenanceMessageEn(?string $maintenanceMessageEn): static
    {
        $this->maintenanceMessageEn = $maintenanceMessageEn;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getMaintenanceAllowedIps(): array
    {
        return $this->maintenanceAllowedIps;
    }

    /**
     * @param list<string> $maintenanceAllowedIps
     */
    public function setMaintenanceAllowedIps(array $maintenanceAllowedIps): static
    {
        $this->maintenanceAllowedIps = $maintenanceAllowedIps;

        return $this;
    }

    public function getMaintenanceAllowedIpsAsString(): string
    {
        return \implode(', ', $this->maintenanceAllowedIps);
    }

    public function setMaintenanceAllowedIpsAsString(?string $value): static
    {
        if ($value === null || \trim($value) === '') {
            $this->maintenanceAllowedIps = [];

            return $this;
        }

        $this->maintenanceAllowedIps = \array_values(\array_filter(
            \array_map('trim', \explode(',', $value)),
            static fn (string $ip): bool => $ip !== '',
        ));

        return $this;
    }
}
