<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MessengerMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MessengerMessageRepository::class, readOnly: true)]
#[ORM\Table(name: 'messenger_messages')]
class MessengerMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\Column(type: 'text')]
    private string $body = '';

    #[ORM\Column(type: 'text')]
    private string $headers = '';

    #[ORM\Column(length: 190)]
    private string $queueName = '';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $availableAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->availableAt = new \DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getHeaders(): string
    {
        return $this->headers;
    }

    /**
     * Extracts the short class name of the message from headers JSON.
     */
    public function getMessageType(): string
    {
        $decoded = \json_decode($this->headers, true);
        $type = $decoded['type'] ?? '';

        if (\str_contains($type, '\\')) {
            return \substr($type, \strrpos($type, '\\') + 1);
        }

        return $type ?: '—';
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getAvailableAt(): \DateTimeImmutable
    {
        return $this->availableAt;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function isDelivered(): bool
    {
        return $this->deliveredAt !== null;
    }

    public function __toString(): string
    {
        return \sprintf('Message #%s (%s)', $this->id ?? '?', $this->queueName);
    }
}
