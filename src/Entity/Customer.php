<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'customer.email_already_used')]
class Customer implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 100)]
    private string $firstName = '';

    #[ORM\Column(length: 100)]
    private string $lastName = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    /** @var Collection<int, CustomerAddress> */
    #[ORM\OneToMany(targetEntity: CustomerAddress::class, mappedBy: 'customer', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $addresses;

    /** @var Collection<int, Order> */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'customer')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $orders;

    /** @var Collection<int, WishlistItem> */
    #[ORM\OneToMany(targetEntity: WishlistItem::class, mappedBy: 'customer', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $wishlistItems;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->addresses = new ArrayCollection();
        $this->orders = new ArrayCollection();
        $this->wishlistItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = \strtolower(\trim($email));

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFullName(): string
    {
        return \trim($this->firstName.' '.$this->lastName);
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_CUSTOMER';

        return \array_values(\array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    /** @return Collection<int, CustomerAddress> */
    public function getAddresses(): Collection
    {
        return $this->addresses;
    }

    public function addAddress(CustomerAddress $address): static
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses->add($address);
            $address->setCustomer($this);
        }

        return $this;
    }

    public function removeAddress(CustomerAddress $address): static
    {
        if ($this->addresses->removeElement($address)) {
            if ($address->getCustomer() === $this) {
                $address->setCustomer(null);
            }
        }

        return $this;
    }

    public function getDefaultAddress(): ?CustomerAddress
    {
        foreach ($this->addresses as $address) {
            if ($address->isDefault()) {
                return $address;
            }
        }

        return $this->addresses->first() ?: null;
    }

    /** @return Collection<int, WishlistItem> */
    public function getWishlistItems(): Collection
    {
        return $this->wishlistItems;
    }

    /** @return Collection<int, Order> */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function getOrderCount(): int
    {
        return $this->orders->count();
    }

    public function getTotalSpentEur(): float
    {
        $total = 0.0;
        foreach ($this->orders as $order) {
            if ($order->getStatus() !== \App\Enum\OrderStatus::Cancelled) {
                $total += (float) $order->getTotalEur();
            }
        }

        return $total;
    }

    public function getLastOrderDate(): ?\DateTimeImmutable
    {
        $latest = null;
        foreach ($this->orders as $order) {
            if ($latest === null || $order->getCreatedAt() > $latest) {
                $latest = $order->getCreatedAt();
            }
        }

        return $latest;
    }

    public function getLoyaltyBadge(): string
    {
        $count = $this->getOrderCount();
        if ($count >= 3) {
            return '<span style="background:#C9A84C;color:#fff;padding:2px 8px;border-radius:4px;font-size:0.75rem;font-weight:600;">VIP</span>';
        }
        if ($count >= 2) {
            return '<span style="background:#8b5cf6;color:#fff;padding:2px 8px;border-radius:4px;font-size:0.75rem;font-weight:600;">Fidèle</span>';
        }

        return '';
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function __toString(): string
    {
        return $this->getFullName() ?: $this->email;
    }
}
