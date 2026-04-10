<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $reference = '';

    #[ORM\Column(length: 20, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::Pending;

    #[ORM\Column(length: 255)]
    private string $customerEmail = '';

    #[ORM\Column(length: 255)]
    private string $customerName = '';

    #[ORM\Column(length: 255)]
    private string $shippingAddressLine1 = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $shippingAddressLine2 = null;

    #[ORM\Column(length: 255)]
    private string $shippingCity = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $shippingState = null;

    #[ORM\Column(length: 20)]
    private string $shippingPostalCode = '';

    #[ORM\Column(length: 2)]
    private string $shippingCountry = '';

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $originCountry = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalUsd = '0.00';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $stripePaymentStatus = null;

    /** @var Collection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->reference;
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

    public function getReference(): string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    /**
     * Generate a unique order reference: ASP-YYYY-XXXXX.
     */
    public static function generateReference(): string
    {
        return \sprintf('ASP-%s-%05d', \date('Y'), \random_int(1, 99999));
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(string $customerEmail): static
    {
        $this->customerEmail = $customerEmail;

        return $this;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function setCustomerName(string $customerName): static
    {
        $this->customerName = $customerName;

        return $this;
    }

    public function getShippingAddressLine1(): string
    {
        return $this->shippingAddressLine1;
    }

    public function setShippingAddressLine1(string $shippingAddressLine1): static
    {
        $this->shippingAddressLine1 = $shippingAddressLine1;

        return $this;
    }

    public function getShippingAddressLine2(): ?string
    {
        return $this->shippingAddressLine2;
    }

    public function setShippingAddressLine2(?string $shippingAddressLine2): static
    {
        $this->shippingAddressLine2 = $shippingAddressLine2;

        return $this;
    }

    public function getShippingCity(): string
    {
        return $this->shippingCity;
    }

    public function setShippingCity(string $shippingCity): static
    {
        $this->shippingCity = $shippingCity;

        return $this;
    }

    public function getShippingState(): ?string
    {
        return $this->shippingState;
    }

    public function setShippingState(?string $shippingState): static
    {
        $this->shippingState = $shippingState;

        return $this;
    }

    public function getShippingPostalCode(): string
    {
        return $this->shippingPostalCode;
    }

    public function setShippingPostalCode(string $shippingPostalCode): static
    {
        $this->shippingPostalCode = $shippingPostalCode;

        return $this;
    }

    public function getShippingCountry(): string
    {
        return $this->shippingCountry;
    }

    public function setShippingCountry(string $shippingCountry): static
    {
        $this->shippingCountry = \strtoupper($shippingCountry);

        return $this;
    }

    public function getOriginCountry(): ?string
    {
        return $this->originCountry;
    }

    public function setOriginCountry(?string $originCountry): static
    {
        $this->originCountry = null !== $originCountry ? \strtoupper($originCountry) : null;

        return $this;
    }

    public function getTotalUsd(): float
    {
        return (float) $this->totalUsd;
    }

    public function setTotalUsd(float $totalUsd): static
    {
        $this->totalUsd = \number_format($totalUsd, 2, '.', '');

        return $this;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): static
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;

        return $this;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(?string $trackingNumber): static
    {
        $this->trackingNumber = $trackingNumber;

        return $this;
    }

    public function getStripePaymentStatus(): ?string
    {
        return $this->stripePaymentStatus;
    }

    public function setStripePaymentStatus(?string $stripePaymentStatus): static
    {
        $this->stripePaymentStatus = $stripePaymentStatus;

        return $this;
    }

    /** @return Collection<int, OrderItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }

        return $this;
    }

    public function removeItem(OrderItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getOrder() === $this) {
                $item->setOrder(null);
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
