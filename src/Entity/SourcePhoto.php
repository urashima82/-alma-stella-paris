<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PhotoAngle;
use App\Repository\SourcePhotoRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[ORM\Entity(repositoryClass: SourcePhotoRepository::class)]
class SourcePhoto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'sourcePhotos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(length: 500)]
    private string $path = '';

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(length: 20, enumType: PhotoAngle::class)]
    private PhotoAngle $angle = PhotoAngle::Front;

    /** Virtual property for EasyAdmin file upload — not persisted. */
    private ?UploadedFile $sourceFile = null;

    public function __toString(): string
    {
        return \sprintf('Source #%d — %s', $this->position, $this->angle->label());
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getAngle(): PhotoAngle
    {
        return $this->angle;
    }

    public function setAngle(PhotoAngle $angle): static
    {
        $this->angle = $angle;

        return $this;
    }

    public function getSourceFile(): ?UploadedFile
    {
        return $this->sourceFile;
    }

    public function setSourceFile(?UploadedFile $sourceFile): static
    {
        $this->sourceFile = $sourceFile;

        return $this;
    }
}
