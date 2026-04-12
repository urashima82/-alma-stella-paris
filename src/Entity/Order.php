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

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Customer $customer = null;

    #[ORM\Column(length: 255)]
    private string $customerEmail = '';

    #[ORM\Column(length: 255)]
    private string $customerName = '';

    #[ORM\Column(length: 255)]
    private string $shippingRecipientName = '';

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

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billingRecipientName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billingAddressLine1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billingAddressLine2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $billingCity = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $billingState = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $billingPostalCode = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $billingCountry = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $totalUsd = '0.00';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(length: 5, nullable: true)]
    private ?string $customerLocale = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $trackingNumber = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $stripePaymentStatus = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $internalNotes = null;

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

    public function getStatusLabel(): string
    {
        return \sprintf(
            '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:500;color:%s;border:1px solid %s;background:transparent;">%s</span>',
            $this->status->badgeColor(),
            $this->status->badgeColor(),
            \htmlspecialchars($this->status->label()),
        );
    }

    public function setStatus(OrderStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

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

    public function getShippingRecipientName(): string
    {
        return $this->shippingRecipientName;
    }

    public function setShippingRecipientName(string $shippingRecipientName): static
    {
        $this->shippingRecipientName = $shippingRecipientName;

        return $this;
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

    public function getBillingRecipientName(): ?string
    {
        return $this->billingRecipientName;
    }

    public function setBillingRecipientName(?string $billingRecipientName): static
    {
        $this->billingRecipientName = $billingRecipientName;

        return $this;
    }

    public function getBillingAddressLine1(): ?string
    {
        return $this->billingAddressLine1;
    }

    public function setBillingAddressLine1(?string $billingAddressLine1): static
    {
        $this->billingAddressLine1 = $billingAddressLine1;

        return $this;
    }

    public function getBillingAddressLine2(): ?string
    {
        return $this->billingAddressLine2;
    }

    public function setBillingAddressLine2(?string $billingAddressLine2): static
    {
        $this->billingAddressLine2 = $billingAddressLine2;

        return $this;
    }

    public function getBillingCity(): ?string
    {
        return $this->billingCity;
    }

    public function setBillingCity(?string $billingCity): static
    {
        $this->billingCity = $billingCity;

        return $this;
    }

    public function getBillingState(): ?string
    {
        return $this->billingState;
    }

    public function setBillingState(?string $billingState): static
    {
        $this->billingState = $billingState;

        return $this;
    }

    public function getBillingPostalCode(): ?string
    {
        return $this->billingPostalCode;
    }

    public function setBillingPostalCode(?string $billingPostalCode): static
    {
        $this->billingPostalCode = $billingPostalCode;

        return $this;
    }

    public function getBillingCountry(): ?string
    {
        return $this->billingCountry;
    }

    public function setBillingCountry(?string $billingCountry): static
    {
        $this->billingCountry = null !== $billingCountry ? \strtoupper($billingCountry) : null;

        return $this;
    }

    public function hasSeparateBillingAddress(): bool
    {
        return null !== $this->billingAddressLine1;
    }

    public function getFullBillingAddress(): string
    {
        if (!$this->hasSeparateBillingAddress()) {
            return $this->getFullShippingAddress();
        }

        return \implode(', ', \array_filter([
            $this->billingAddressLine1,
            $this->billingAddressLine2,
            $this->billingPostalCode.' '.$this->billingCity,
            $this->billingState,
            $this->billingCountry,
        ]));
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

    public function getCustomerLocale(): ?string
    {
        return $this->customerLocale;
    }

    public function setCustomerLocale(?string $customerLocale): static
    {
        $this->customerLocale = $customerLocale;

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

    public function getInternalNotes(): ?string
    {
        return $this->internalNotes;
    }

    public function setInternalNotes(?string $internalNotes): static
    {
        $this->internalNotes = $internalNotes;

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

    public function getItemCount(): int
    {
        return $this->items->count();
    }

    public function getFullShippingAddress(): string
    {
        return \implode(', ', \array_filter([
            $this->shippingAddressLine1,
            $this->shippingAddressLine2,
            $this->shippingPostalCode.' '.$this->shippingCity,
            $this->shippingState,
            $this->shippingCountry,
        ]));
    }

    public function getItemsSummary(): string
    {
        if ($this->items->isEmpty()) {
            return '<em class="text-body-secondary">Aucun article</em>';
        }

        $rows = '';
        foreach ($this->items as $item) {
            $rows .= \sprintf(
                '<tr><td style="padding:6px 12px 6px 0;">%s</td><td style="padding:6px 8px;text-align:right;">$%s</td><td class="text-body-secondary" style="padding:6px 8px;text-align:right;">$%s</td><td style="padding:6px 0 6px 8px;text-align:right;font-weight:600;">$%s</td></tr>',
                \htmlspecialchars($item->getProductName()),
                \number_format($item->getProductPrice(), 2),
                \number_format($item->getShippingCost(), 2),
                \number_format($item->getLineTotal(), 2),
            );
        }

        return \sprintf(
            '<table style="width:100%%;border-collapse:collapse;font-size:13px;"><thead><tr style="border-bottom:2px solid var(--bs-border-color);"><th class="text-body-secondary" style="padding:6px 12px 6px 0;text-align:left;font-weight:600;">Produit</th><th class="text-body-secondary" style="padding:6px 8px;text-align:right;font-weight:600;">Prix</th><th class="text-body-secondary" style="padding:6px 8px;text-align:right;font-weight:600;">Livraison</th><th class="text-body-secondary" style="padding:6px 0 6px 8px;text-align:right;font-weight:600;">Total</th></tr></thead><tbody>%s</tbody><tfoot><tr style="border-top:2px solid var(--bs-border-color);"><td style="padding:8px 12px 0 0;font-weight:700;">Total</td><td colspan="2"></td><td style="padding:8px 0 0 8px;text-align:right;font-weight:700;font-size:14px;">$%s</td></tr></tfoot></table>',
            $rows,
            \number_format($this->getTotalUsd(), 2),
        );
    }

    public function getTrackingLink(): ?string
    {
        return $this->trackingNumber;
    }

    public function getPublicTrackingPage(): string
    {
        return $this->reference;
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
