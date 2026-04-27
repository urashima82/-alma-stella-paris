<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\VisualStatus;
use App\Enum\VisualType;
use App\Repository\GeneratedVisualRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GeneratedVisualRepository::class)]
class GeneratedVisual
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'generatedVisuals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(length: 20, enumType: VisualType::class)]
    private VisualType $type = VisualType::Vignette;

    #[ORM\Column(length: 500)]
    private string $path = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $promptUsed = '';

    #[ORM\Column]
    private int $categoryPromptVersion = 0;

    #[ORM\Column(length: 20, enumType: VisualStatus::class)]
    private VisualStatus $status = VisualStatus::Generating;

    #[ORM\Column]
    private int $variant = 1;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $geminiRequestId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modelUsed = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return \sprintf('%s v%d — %s', $this->type->label(), $this->variant, $this->status->label());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getType(): VisualType
    {
        return $this->type;
    }

    public function setType(VisualType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getPromptUsed(): string
    {
        return $this->promptUsed;
    }

    public function setPromptUsed(string $promptUsed): static
    {
        $this->promptUsed = $promptUsed;

        return $this;
    }

    public function getCategoryPromptVersion(): int
    {
        return $this->categoryPromptVersion;
    }

    public function setCategoryPromptVersion(int $categoryPromptVersion): static
    {
        $this->categoryPromptVersion = $categoryPromptVersion;

        return $this;
    }

    public function getStatus(): VisualStatus
    {
        return $this->status;
    }

    public function setStatus(VisualStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getVariant(): int
    {
        return $this->variant;
    }

    public function setVariant(int $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    public function getGeminiRequestId(): ?string
    {
        return $this->geminiRequestId;
    }

    public function setGeminiRequestId(?string $geminiRequestId): static
    {
        $this->geminiRequestId = $geminiRequestId;

        return $this;
    }

    public function getModelUsed(): ?string
    {
        return $this->modelUsed;
    }

    public function setModelUsed(?string $modelUsed): static
    {
        $this->modelUsed = $modelUsed;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
