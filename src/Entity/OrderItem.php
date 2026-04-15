<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Product $product = null;

    #[ORM\Column(length: 255)]
    private string $productName = '';

    #[ORM\Column(length: 255)]
    private string $productNameFr = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $productPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $shippingCost = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $discountAmountEur = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

        return $this;
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

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

        return $this;
    }

    public function getProductNameFr(): string
    {
        return $this->productNameFr;
    }

    public function setProductNameFr(string $productNameFr): static
    {
        $this->productNameFr = $productNameFr;

        return $this;
    }

    public function getLocalizedProductName(string $locale = 'en'): string
    {
        if ($locale === 'fr' && $this->productNameFr !== '') {
            return $this->productNameFr;
        }

        return $this->productName;
    }

    public function getProductPrice(): float
    {
        return (float) $this->productPrice;
    }

    public function setProductPrice(float $productPrice): static
    {
        $this->productPrice = \number_format($productPrice, 2, '.', '');

        return $this;
    }

    public function getShippingCost(): float
    {
        return (float) $this->shippingCost;
    }

    public function setShippingCost(float $shippingCost): static
    {
        $this->shippingCost = \number_format($shippingCost, 2, '.', '');

        return $this;
    }

    public function getDiscountAmountEur(): float
    {
        return (float) $this->discountAmountEur;
    }

    public function setDiscountAmountEur(float $discountAmountEur): static
    {
        $this->discountAmountEur = \number_format($discountAmountEur, 2, '.', '');

        return $this;
    }

    /**
     * Total line price (product price + shipping cost - discount) in EUR.
     */
    public function getLineTotal(): float
    {
        return $this->getProductPrice() + $this->getShippingCost() - $this->getDiscountAmountEur();
    }

    /**
     * Create an OrderItem from a Product, snapshotting current prices.
     */
    public static function fromProduct(Product $product, float $shippingCost): self
    {
        $item = new self();
        $item->setProduct($product);
        $item->setProductName($product->getName());
        $item->setProductNameFr($product->getNameFr());
        $item->setProductPrice($product->getBasePrice());
        $item->setShippingCost($shippingCost);

        return $item;
    }
}
