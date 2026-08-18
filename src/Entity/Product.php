<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ShippingTier;
use App\Enum\VisualStatus;
use App\Enum\VisualType;
use App\Enum\VisualWorkflowStatus;
use App\Repository\ProductRepository;
use App\Validator\LeafCategory;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Product
{
    public const string COUNTRY_FRANCE = 'france';
    public const string COUNTRY_MEXICO = 'mexico';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    private string $nameFr = '';

    #[ORM\Column(length: 280, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 280, unique: true)]
    private string $slugFr = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $descriptionFr = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $basePrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $compareAtPrice = null;

    #[ORM\Column(length: 20, enumType: ShippingTier::class)]
    private ShippingTier $shippingTier = ShippingTier::Standard;

    #[ORM\ManyToOne(targetEntity: ProductCategory::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    #[LeafCategory]
    private ?ProductCategory $category = null;

    #[ORM\Column]
    private bool $isPublished = false;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column]
    private bool $isSoldOut = false;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    private array $availableIn = [self::COUNTRY_FRANCE];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $soldAt = null;

    #[Vich\UploadableField(mapping: 'product_images', fileNameProperty: 'thumbnail')]
    #[Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])]
    private ?File $thumbnailFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $thumbnail = null;

    #[Vich\UploadableField(mapping: 'product_images', fileNameProperty: 'wornPhoto')]
    #[Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])]
    private ?File $wornPhotoFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $wornPhoto = null;

    #[Vich\UploadableField(mapping: 'product_images', fileNameProperty: 'contextPhoto')]
    #[Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])]
    private ?File $contextPhotoFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contextPhoto = null;

    /** @var Collection<int, self> */
    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'product_related')]
    private Collection $relatedProducts;

    /** @var Collection<int, Stone> */
    #[ORM\ManyToMany(targetEntity: Stone::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'product_stone')]
    private Collection $stones;

    #[ORM\Column(length: 30, enumType: VisualWorkflowStatus::class)]
    private VisualWorkflowStatus $visualStatus = VisualWorkflowStatus::Draft;

    /** @var Collection<int, SourcePhoto> */
    #[ORM\OneToMany(targetEntity: SourcePhoto::class, mappedBy: 'product', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $sourcePhotos;

    /** @var Collection<int, GeneratedVisual> */
    #[ORM\OneToMany(targetEntity: GeneratedVisual::class, mappedBy: 'product', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['type' => 'ASC', 'variant' => 'ASC'])]
    private Collection $generatedVisuals;

    /** @var Collection<int, ProductContentSuggestion> */
    #[ORM\OneToMany(targetEntity: ProductContentSuggestion::class, mappedBy: 'product', cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['generatedAt' => 'DESC'])]
    private Collection $contentSuggestions;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->relatedProducts = new ArrayCollection();
        $this->stones = new ArrayCollection();
        $this->sourcePhotos = new ArrayCollection();
        $this->generatedVisuals = new ArrayCollection();
        $this->contentSuggestions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validatePublishedPrice(ExecutionContextInterface $context): void
    {
        if ($this->isPublished && $this->getBasePrice() <= 0) {
            $context->buildViolation('Un produit publié doit avoir un prix supérieur à 0 €.')
                ->atPath('basePrice')
                ->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        if ($this->slug === '') {
            $this->slug = (string) (new AsciiSlugger())->slug($name)->lower();
        }

        return $this;
    }

    public function getNameFr(): string
    {
        return $this->nameFr;
    }

    public function setNameFr(string $nameFr): static
    {
        $this->nameFr = $nameFr;

        if ($this->slugFr === '') {
            $this->slugFr = (string) (new AsciiSlugger('fr'))->slug($nameFr)->lower();
        }

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlugFr(): string
    {
        return $this->slugFr;
    }

    public function setSlugFr(string $slugFr): static
    {
        $this->slugFr = $slugFr;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescriptionFr(): string
    {
        return $this->descriptionFr;
    }

    public function setDescriptionFr(string $descriptionFr): static
    {
        $this->descriptionFr = $descriptionFr;

        return $this;
    }

    public function getBasePrice(): float
    {
        return (float) $this->basePrice;
    }

    public function setBasePrice(float $basePrice): static
    {
        $this->basePrice = \number_format($basePrice, 2, '.', '');

        return $this;
    }

    public function getCompareAtPrice(): ?float
    {
        return $this->compareAtPrice !== null ? (float) $this->compareAtPrice : null;
    }

    public function setCompareAtPrice(?float $compareAtPrice): static
    {
        $this->compareAtPrice = $compareAtPrice !== null
            ? \number_format($compareAtPrice, 2, '.', '')
            : null;

        return $this;
    }

    public function getDiscountPercent(): ?int
    {
        if ($this->compareAtPrice === null || (float) $this->compareAtPrice <= $this->getBasePrice()) {
            return null;
        }

        return (int) \round(100 - ($this->getBasePrice() / (float) $this->compareAtPrice * 100));
    }

    public function getShippingTier(): ShippingTier
    {
        return $this->shippingTier;
    }

    public function setShippingTier(ShippingTier $shippingTier): static
    {
        $this->shippingTier = $shippingTier;

        return $this;
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

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): static
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    public function isSoldOut(): bool
    {
        return $this->isSoldOut;
    }

    public function setIsSoldOut(bool $isSoldOut): static
    {
        $this->isSoldOut = $isSoldOut;

        if ($isSoldOut && $this->soldAt === null) {
            $this->soldAt = new \DateTimeImmutable();
        }

        if (!$isSoldOut) {
            $this->soldAt = null;
        }

        return $this;
    }

    /** @return string[] */
    public function getAvailableIn(): array
    {
        return $this->availableIn;
    }

    /** @param string[] $availableIn */
    public function setAvailableIn(array $availableIn): static
    {
        $this->availableIn = $availableIn;

        return $this;
    }

    /**
     * Each unique piece sits in a single location; Mexico drives the longer
     * delivery estimate shown across the shop and transactional emails.
     */
    public function isLocatedInMexico(): bool
    {
        return \in_array(self::COUNTRY_MEXICO, $this->availableIn, true);
    }

    public function getSoldAt(): ?\DateTimeImmutable
    {
        return $this->soldAt;
    }

    public function isNew(int $days = 14): bool
    {
        return $this->createdAt > new \DateTimeImmutable(\sprintf('-%d days', $days));
    }

    public function isVisibleInCatalog(int $soldVisibilityDays = 14): bool
    {
        if (!$this->isPublished) {
            return false;
        }

        if (!$this->isSoldOut) {
            return true;
        }

        if ($this->soldAt === null) {
            return false;
        }

        return $this->soldAt > new \DateTimeImmutable(\sprintf('-%d days', $soldVisibilityDays));
    }

    public function getThumbnailFile(): ?File
    {
        return $this->thumbnailFile;
    }

    public function setThumbnailFile(?File $thumbnailFile): static
    {
        $this->thumbnailFile = $thumbnailFile;

        if ($thumbnailFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getThumbnail(): ?string
    {
        return $this->thumbnail;
    }

    public function setThumbnail(?string $thumbnail): static
    {
        $this->thumbnail = $thumbnail;

        return $this;
    }

    public function getWornPhotoFile(): ?File
    {
        return $this->wornPhotoFile;
    }

    public function setWornPhotoFile(?File $wornPhotoFile): static
    {
        $this->wornPhotoFile = $wornPhotoFile;

        if ($wornPhotoFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getWornPhoto(): ?string
    {
        return $this->wornPhoto;
    }

    public function setWornPhoto(?string $wornPhoto): static
    {
        $this->wornPhoto = $wornPhoto;

        return $this;
    }

    public function getContextPhotoFile(): ?File
    {
        return $this->contextPhotoFile;
    }

    public function setContextPhotoFile(?File $contextPhotoFile): static
    {
        $this->contextPhotoFile = $contextPhotoFile;

        if ($contextPhotoFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getContextPhoto(): ?string
    {
        return $this->contextPhoto;
    }

    public function setContextPhoto(?string $contextPhoto): static
    {
        $this->contextPhoto = $contextPhoto;

        return $this;
    }

    /** @return Collection<int, self> */
    public function getRelatedProducts(): Collection
    {
        return $this->relatedProducts;
    }

    public function addRelatedProduct(self $product): static
    {
        if (!$this->relatedProducts->contains($product)) {
            $this->relatedProducts->add($product);
        }

        return $this;
    }

    public function removeRelatedProduct(self $product): static
    {
        $this->relatedProducts->removeElement($product);

        return $this;
    }

    /** @return Collection<int, Stone> */
    public function getStones(): Collection
    {
        return $this->stones;
    }

    public function addStone(Stone $stone): static
    {
        if (!$this->stones->contains($stone)) {
            $this->stones->add($stone);
        }

        return $this;
    }

    public function removeStone(Stone $stone): static
    {
        $this->stones->removeElement($stone);

        return $this;
    }

    public function getVisualStatus(): VisualWorkflowStatus
    {
        return $this->visualStatus;
    }

    public function setVisualStatus(VisualWorkflowStatus $visualStatus): static
    {
        $this->visualStatus = $visualStatus;

        return $this;
    }

    /** @return Collection<int, SourcePhoto> */
    public function getSourcePhotos(): Collection
    {
        return $this->sourcePhotos;
    }

    public function addSourcePhoto(SourcePhoto $sourcePhoto): static
    {
        if (!$this->sourcePhotos->contains($sourcePhoto)) {
            $this->sourcePhotos->add($sourcePhoto);
            $sourcePhoto->setProduct($this);
        }

        return $this;
    }

    public function removeSourcePhoto(SourcePhoto $sourcePhoto): static
    {
        if ($this->sourcePhotos->removeElement($sourcePhoto)) {
            if ($sourcePhoto->getProduct() === $this) {
                $sourcePhoto->setProduct(null);
            }
        }

        return $this;
    }

    /** @return Collection<int, GeneratedVisual> */
    public function getGeneratedVisuals(): Collection
    {
        return $this->generatedVisuals;
    }

    public function addGeneratedVisual(GeneratedVisual $visual): static
    {
        if (!$this->generatedVisuals->contains($visual)) {
            $this->generatedVisuals->add($visual);
            $visual->setProduct($this);
        }

        return $this;
    }

    public function removeGeneratedVisual(GeneratedVisual $visual): static
    {
        if ($this->generatedVisuals->removeElement($visual)) {
            if ($visual->getProduct() === $this) {
                $visual->setProduct(null);
            }
        }

        return $this;
    }

    public function getApprovedVisualFor(VisualType $type): ?GeneratedVisual
    {
        foreach ($this->generatedVisuals as $visual) {
            if ($visual->getType() === $type && $visual->getStatus() === VisualStatus::Approved) {
                return $visual;
            }
        }

        return null;
    }

    /** @return Collection<int, ProductContentSuggestion> */
    public function getContentSuggestions(): Collection
    {
        return $this->contentSuggestions;
    }

    public function addContentSuggestion(ProductContentSuggestion $suggestion): static
    {
        if (!$this->contentSuggestions->contains($suggestion)) {
            $this->contentSuggestions->add($suggestion);
            $suggestion->setProduct($this);
        }

        return $this;
    }

    public function removeContentSuggestion(ProductContentSuggestion $suggestion): static
    {
        if ($this->contentSuggestions->removeElement($suggestion)) {
            if ($suggestion->getProduct() === $this) {
                $suggestion->setProduct(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
