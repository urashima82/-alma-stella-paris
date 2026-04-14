<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TestimonialStatus;
use App\Repository\TestimonialRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TestimonialRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Testimonial
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $email = '';

    #[ORM\Column(length: 36, unique: true)]
    private string $token = '';

    #[ORM\Column(nullable: true)]
    private ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $text = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 1, nullable: true)]
    private ?string $lastNameInitial = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 20, enumType: TestimonialStatus::class)]
    private TestimonialStatus $status = TestimonialStatus::Pending;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Order $relatedOrder = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $submittedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->token = Uuid::v4()->toRfc4122();
    }

    public function __toString(): string
    {
        return $this->email;
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

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastNameInitial(): ?string
    {
        return $this->lastNameInitial;
    }

    public function setLastNameInitial(?string $lastNameInitial): static
    {
        $this->lastNameInitial = $lastNameInitial !== null ? \strtoupper(\mb_substr($lastNameInitial, 0, 1)) : null;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getStatus(): TestimonialStatus
    {
        return $this->status;
    }

    public function setStatus(TestimonialStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRelatedOrder(): ?Order
    {
        return $this->relatedOrder;
    }

    public function setRelatedOrder(?Order $relatedOrder): static
    {
        $this->relatedOrder = $relatedOrder;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getSubmittedAt(): ?\DateTimeImmutable
    {
        return $this->submittedAt;
    }

    public function setSubmittedAt(?\DateTimeImmutable $submittedAt): static
    {
        $this->submittedAt = $submittedAt;

        return $this;
    }

    public function isSubmitted(): bool
    {
        return $this->submittedAt !== null;
    }

    public function getDisplayName(): string
    {
        if ($this->firstName === null) {
            return '';
        }

        $name = $this->firstName;
        if ($this->lastNameInitial !== null) {
            $name .= ' '.$this->lastNameInitial.'.';
        }

        return $name;
    }
}
