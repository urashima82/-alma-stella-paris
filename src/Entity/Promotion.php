<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DiscountType;
use App\Enum\PromotionType;
use App\Repository\PromotionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PromotionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Promotion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(length: 30, enumType: PromotionType::class)]
    private PromotionType $type = PromotionType::CartCode;

    #[ORM\Column(length: 20, enumType: DiscountType::class)]
    private DiscountType $discountType = DiscountType::Percentage;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $discountValue = '0.00';

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isCumulable = false;

    #[ORM\Column]
    private bool $overridesCompareAtPrice = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxUsages = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxUsagesPerEmail = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $minimumAmountUsd = null;

    /** @var Collection<int, Product> */
    #[ORM\ManyToMany(targetEntity: Product::class)]
    #[ORM\JoinTable(name: 'promotion_product')]
    private Collection $products;

    /** @var Collection<int, ProductCategory> */
    #[ORM\ManyToMany(targetEntity: ProductCategory::class)]
    #[ORM\JoinTable(name: 'promotion_category')]
    private Collection $categories;

    /** @var Collection<int, PromotionUsage> */
    #[ORM\OneToMany(targetEntity: PromotionUsage::class, mappedBy: 'promotion', cascade: ['remove'])]
    private Collection $usages;

    #[ORM\Column]
    private int $usageCount = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $revenueGeneratedUsd = '0.00';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->usages = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Virtual getter for EasyAdmin — returns the code (display handled by formatValue).
     */
    public function getShareableLink(): ?string
    {
        return $this->code;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): static
    {
        $this->code = null !== $code ? \strtoupper(\trim($code)) : null;

        return $this;
    }

    public function getType(): PromotionType
    {
        return $this->type;
    }

    public function getTypeLabel(): string
    {
        return $this->type->label();
    }

    public function setType(PromotionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDiscountType(): DiscountType
    {
        return $this->discountType;
    }

    public function getDiscountTypeLabel(): string
    {
        return $this->discountType->label();
    }

    public function setDiscountType(DiscountType $discountType): static
    {
        $this->discountType = $discountType;

        return $this;
    }

    public function getDiscountValue(): float
    {
        return (float) $this->discountValue;
    }

    public function setDiscountValue(float $discountValue): static
    {
        $this->discountValue = \number_format($discountValue, 2, '.', '');

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isCumulable(): bool
    {
        return $this->isCumulable;
    }

    public function setIsCumulable(bool $isCumulable): static
    {
        $this->isCumulable = $isCumulable;

        return $this;
    }

    public function overridesCompareAtPrice(): bool
    {
        return $this->overridesCompareAtPrice;
    }

    public function setOverridesCompareAtPrice(bool $overridesCompareAtPrice): static
    {
        $this->overridesCompareAtPrice = $overridesCompareAtPrice;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(?\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(?\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function getMaxUsages(): ?int
    {
        return $this->maxUsages;
    }

    public function setMaxUsages(?int $maxUsages): static
    {
        $this->maxUsages = $maxUsages;

        return $this;
    }

    public function getMaxUsagesPerEmail(): ?int
    {
        return $this->maxUsagesPerEmail;
    }

    public function setMaxUsagesPerEmail(?int $maxUsagesPerEmail): static
    {
        $this->maxUsagesPerEmail = $maxUsagesPerEmail;

        return $this;
    }

    public function getMinimumAmountUsd(): ?float
    {
        return null !== $this->minimumAmountUsd ? (float) $this->minimumAmountUsd : null;
    }

    public function setMinimumAmountUsd(?float $minimumAmountUsd): static
    {
        $this->minimumAmountUsd = null !== $minimumAmountUsd
            ? \number_format($minimumAmountUsd, 2, '.', '')
            : null;

        return $this;
    }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        $this->products->removeElement($product);

        return $this;
    }

    /** @return Collection<int, ProductCategory> */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(ProductCategory $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(ProductCategory $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    /** @return Collection<int, PromotionUsage> */
    public function getUsages(): Collection
    {
        return $this->usages;
    }

    public function getUsageCount(): int
    {
        return $this->usageCount;
    }

    public function incrementUsageCount(): static
    {
        ++$this->usageCount;

        return $this;
    }

    public function getRevenueGeneratedUsd(): float
    {
        return (float) $this->revenueGeneratedUsd;
    }

    public function addRevenue(float $amount): static
    {
        $this->revenueGeneratedUsd = \number_format(
            (float) $this->revenueGeneratedUsd + $amount,
            2,
            '.',
            '',
        );

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

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

    /**
     * Check if this promotion is currently valid (date range + active flag).
     */
    public function isCurrentlyValid(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $now = new \DateTimeImmutable();

        if (null !== $this->startsAt && $now < $this->startsAt) {
            return false;
        }

        if (null !== $this->endsAt && $now > $this->endsAt) {
            return false;
        }

        return true;
    }

    /**
     * Check if the total usage limit has been reached.
     */
    public function hasReachedMaxUsages(): bool
    {
        if (null === $this->maxUsages) {
            return false;
        }

        return $this->usageCount >= $this->maxUsages;
    }

    /**
     * Check if this promotion applies to a given product.
     * An empty products + categories list means "applies to all".
     */
    public function appliesToProduct(Product $product): bool
    {
        $hasProductRestriction = !$this->products->isEmpty();
        $hasCategoryRestriction = !$this->categories->isEmpty();

        if (!$hasProductRestriction && !$hasCategoryRestriction) {
            return true;
        }

        if ($hasProductRestriction && $this->products->contains($product)) {
            return true;
        }

        if ($hasCategoryRestriction && null !== $product->getCategory()
            && $this->categories->contains($product->getCategory())) {
            return true;
        }

        return false;
    }

    /**
     * Check if this promotion can apply to a product considering its compareAtPrice.
     */
    public function canApplyToProductWithCompareAtPrice(Product $product): bool
    {
        if (null === $product->getCompareAtPrice()) {
            return true;
        }

        return $this->overridesCompareAtPrice;
    }

    /**
     * Calculate the discount amount for a given price.
     */
    public function calculateDiscount(float $price): float
    {
        return match ($this->discountType) {
            DiscountType::Percentage => \round($price * $this->getDiscountValue() / 100, 2),
            DiscountType::FixedAmount => \min($this->getDiscountValue(), $price),
        };
    }

    public function getDiscountLabel(): string
    {
        return match ($this->discountType) {
            DiscountType::Percentage => \sprintf('-%g%%', $this->getDiscountValue()),
            DiscountType::FixedAmount => \sprintf('-$%s', \number_format($this->getDiscountValue(), 2)),
        };
    }
}
