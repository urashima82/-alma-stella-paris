<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\VisualType;
use App\Repository\CategoryVisualPromptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CategoryVisualPromptRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CATEGORY_VISUAL_PROMPT', columns: ['category_id', 'visual_type', 'version'])]
class CategoryVisualPrompt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProductCategory::class, inversedBy: 'visualPrompts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProductCategory $category = null;

    #[ORM\Column(length: 20, enumType: VisualType::class)]
    private VisualType $visualType = VisualType::Vignette;

    #[ORM\Column(type: Types::TEXT)]
    private string $framing = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $staging = '';

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    private array $props = [];

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private int $version = 1;

    public function __toString(): string
    {
        return \sprintf('%s — %s (v%d)', $this->category ?? '?', $this->visualType->label(), $this->version);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?ProductCategory
    {
        return $this->category;
    }

    public function setCategory(?ProductCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getVisualType(): VisualType
    {
        return $this->visualType;
    }

    public function setVisualType(VisualType $visualType): static
    {
        $this->visualType = $visualType;

        return $this;
    }

    public function getFraming(): string
    {
        return $this->framing;
    }

    public function setFraming(string $framing): static
    {
        $this->framing = $framing;

        return $this;
    }

    public function getStaging(): string
    {
        return $this->staging;
    }

    public function setStaging(string $staging): static
    {
        $this->staging = $staging;

        return $this;
    }

    /** @return string[] */
    public function getProps(): array
    {
        return $this->props;
    }

    /** @param string[] $props */
    public function setProps(array $props): static
    {
        $this->props = $props;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;

        return $this;
    }
}
