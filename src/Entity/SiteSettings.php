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
}
