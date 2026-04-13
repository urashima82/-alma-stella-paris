<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PromotionUsageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PromotionUsageRepository::class)]
class PromotionUsage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Promotion::class, inversedBy: 'usages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Promotion $promotion = null;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $order = null;

    #[ORM\Column(length: 255)]
    private string $customerEmail = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $discountAmountUsd = '0.00';

    #[ORM\Column]
    private \DateTimeImmutable $usedAt;

    public function __construct()
    {
        $this->usedAt = new \DateTimeImmutable();
    }

    public static function create(Promotion $promotion, Order $order, string $email, float $discountAmount): self
    {
        $usage = new self();
        $usage->promotion = $promotion;
        $usage->order = $order;
        $usage->customerEmail = $email;
        $usage->discountAmountUsd = \number_format($discountAmount, 2, '.', '');

        return $usage;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPromotion(): ?Promotion
    {
        return $this->promotion;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function getCustomerEmail(): string
    {
        return $this->customerEmail;
    }

    public function getDiscountAmountUsd(): float
    {
        return (float) $this->discountAmountUsd;
    }

    public function getUsedAt(): \DateTimeImmutable
    {
        return $this->usedAt;
    }
}
