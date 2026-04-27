<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContentSuggestionStatus;
use App\Repository\ProductContentSuggestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductContentSuggestionRepository::class)]
class ProductContentSuggestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'contentSuggestions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameEn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nameFr = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionEn = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $descriptionFr = null;

    #[ORM\Column(length: 20, enumType: ContentSuggestionStatus::class)]
    private ContentSuggestionStatus $status = ContentSuggestionStatus::Pending;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $modelUsed = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $requestId = null;

    /**
     * Snapshot of the taxonomy context (category, stones) at the moment the
     * call was made, so the manager can see what the AI was given even after
     * the product is later edited.
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $contextSnapshot = null;

    /**
     * Free-form steering text passed when the manager hits "Regenerate" with
     * a tweak ("more poetic", "emphasise the blue"). Null on first generation.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $additionalContext = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column]
    private \DateTimeImmutable $generatedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $appliedAt = null;

    public function __construct()
    {
        $this->generatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        $product = $this->product?->getNameFr() ?? '—';

        return \sprintf('%s — %s', $product, $this->status->label());
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

    public function getNameEn(): ?string
    {
        return $this->nameEn;
    }

    public function setNameEn(?string $nameEn): static
    {
        $this->nameEn = $nameEn;

        return $this;
    }

    public function getNameFr(): ?string
    {
        return $this->nameFr;
    }

    public function setNameFr(?string $nameFr): static
    {
        $this->nameFr = $nameFr;

        return $this;
    }

    public function getDescriptionEn(): ?string
    {
        return $this->descriptionEn;
    }

    public function setDescriptionEn(?string $descriptionEn): static
    {
        $this->descriptionEn = $descriptionEn;

        return $this;
    }

    public function getDescriptionFr(): ?string
    {
        return $this->descriptionFr;
    }

    public function setDescriptionFr(?string $descriptionFr): static
    {
        $this->descriptionFr = $descriptionFr;

        return $this;
    }

    public function getStatus(): ContentSuggestionStatus
    {
        return $this->status;
    }

    public function setStatus(ContentSuggestionStatus $status): static
    {
        $this->status = $status;

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

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): static
    {
        $this->requestId = $requestId;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getContextSnapshot(): ?array
    {
        return $this->contextSnapshot;
    }

    /**
     * @param array<string, mixed>|null $contextSnapshot
     */
    public function setContextSnapshot(?array $contextSnapshot): static
    {
        $this->contextSnapshot = $contextSnapshot;

        return $this;
    }

    public function getAdditionalContext(): ?string
    {
        return $this->additionalContext;
    }

    public function setAdditionalContext(?string $additionalContext): static
    {
        $this->additionalContext = $additionalContext;

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

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function getAppliedAt(): ?\DateTimeImmutable
    {
        return $this->appliedAt;
    }

    public function setAppliedAt(?\DateTimeImmutable $appliedAt): static
    {
        $this->appliedAt = $appliedAt;

        return $this;
    }
}
