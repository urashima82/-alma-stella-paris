<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GeminiOperation;
use App\Repository\GeminiUsageLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GeminiUsageLogRepository::class)]
class GeminiUsageLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 4)]
    private string $costUsd = '0.0000';

    #[ORM\Column(length: 20, enumType: GeminiOperation::class)]
    private GeminiOperation $operation = GeminiOperation::Visual;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCostUsd(): float
    {
        return (float) $this->costUsd;
    }

    public function setCostUsd(float $costUsd): static
    {
        $this->costUsd = \number_format($costUsd, 4, '.', '');

        return $this;
    }

    public function getOperation(): GeminiOperation
    {
        return $this->operation;
    }

    public function setOperation(GeminiOperation $operation): static
    {
        $this->operation = $operation;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
