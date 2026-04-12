<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CartRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CartRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    /** @var Collection<int, CartItem> */
    #[ORM\OneToMany(targetEntity: CartItem::class, mappedBy: 'cart', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    /** @return Collection<int, CartItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addProduct(Product $product): bool
    {
        foreach ($this->items as $item) {
            if ($item->getProduct()?->getId() === $product->getId()) {
                return false;
            }
        }

        $item = new CartItem();
        $item->setCart($this);
        $item->setProduct($product);
        $this->items->add($item);

        return true;
    }

    public function removeProduct(int $productId): void
    {
        foreach ($this->items as $item) {
            if ($item->getProduct()?->getId() === $productId) {
                $this->items->removeElement($item);

                return;
            }
        }
    }

    public function containsProduct(int $productId): bool
    {
        foreach ($this->items as $item) {
            if ($item->getProduct()?->getId() === $productId) {
                return true;
            }
        }

        return false;
    }

    /** @return int[] */
    public function getProductIds(): array
    {
        $ids = [];
        foreach ($this->items as $item) {
            $id = $item->getProduct()?->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function clear(): void
    {
        $this->items->clear();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
