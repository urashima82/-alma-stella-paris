<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AdminRole;
use App\Repository\AdminRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: AdminRepository::class)]
class Admin implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 20, enumType: AdminRole::class)]
    private AdminRole $role = AdminRole::Admin;

    #[ORM\Column]
    private bool $receivesAdminEmails = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLoggedInAt = null;

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_ADMIN';

        if ($this->role === AdminRole::SuperAdmin) {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }

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

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function eraseCredentials(): void
    {
    }

    public function getLastLoggedInAt(): ?\DateTimeImmutable
    {
        return $this->lastLoggedInAt;
    }

    public function setLastLoggedInAt(?\DateTimeImmutable $lastLoggedInAt): static
    {
        $this->lastLoggedInAt = $lastLoggedInAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRole(): AdminRole
    {
        return $this->role;
    }

    public function setRole(AdminRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === AdminRole::SuperAdmin;
    }

    public function getReceivesAdminEmails(): bool
    {
        return $this->receivesAdminEmails;
    }

    public function setReceivesAdminEmails(bool $receivesAdminEmails): static
    {
        $this->receivesAdminEmails = $receivesAdminEmails;

        return $this;
    }

    public function getRoleLabel(): string
    {
        return \sprintf(
            '<span style="display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:500;color:%s;border:1px solid %s;background:transparent;">%s</span>',
            $this->role->badgeColor(),
            $this->role->badgeColor(),
            \htmlspecialchars($this->role->label()),
        );
    }

    public function __toString(): string
    {
        return $this->email;
    }
}
