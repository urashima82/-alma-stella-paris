<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    public const int DEFAULT_DURATION_MINUTES = 15;
    public const int EXTENSION_DURATION_MINUTES = 10;
    public const int MAX_EXTENSIONS = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column(length: 128)]
    private string $sessionId;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => 0])]
    private int $extensionCount = 0;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * In-memory factory, currently without callers: production code inserts
     * reservations atomically via ReservationRepository::tryInsert() (the
     * unique constraint on product arbitrates concurrent checkouts) and this
     * entity is otherwise only hydrated by Doctrine. Kept so the properties
     * have a legitimate writer; never use it on a production insert path.
     */
    public static function create(Product $product, string $sessionId, int $durationMinutes = self::DEFAULT_DURATION_MINUTES): self
    {
        $reservation = new self();
        $reservation->product = $product;
        $reservation->sessionId = $sessionId;
        $reservation->expiresAt = new \DateTimeImmutable(\sprintf('+%d minutes', $durationMinutes));

        return $reservation;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExtensionCount(): int
    {
        return $this->extensionCount;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }

    public function isOwnedBy(string $sessionId): bool
    {
        return $this->sessionId === $sessionId;
    }

    public function canExtend(): bool
    {
        return $this->extensionCount < self::MAX_EXTENSIONS;
    }

    public function extend(): void
    {
        if (!$this->canExtend()) {
            throw new \LogicException(\sprintf('Reservation already extended %d/%d times.', $this->extensionCount, self::MAX_EXTENSIONS));
        }

        ++$this->extensionCount;
        $this->expiresAt = new \DateTimeImmutable(\sprintf('+%d minutes', self::EXTENSION_DURATION_MINUTES));
    }

    public function getRemainingSeconds(): int
    {
        $remaining = $this->expiresAt->getTimestamp() - (new \DateTimeImmutable())->getTimestamp();

        return \max(0, $remaining);
    }
}
