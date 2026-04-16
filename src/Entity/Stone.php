<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StoneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: StoneRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
class Stone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name = '';

    #[ORM\Column(length: 100)]
    private string $nameFr = '';

    #[ORM\Column(length: 120, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 120, unique: true)]
    private string $slugFr = '';

    #[ORM\Column(length: 255)]
    private string $shortDescription = '';

    #[ORM\Column(length: 255)]
    private string $shortDescriptionFr = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $descriptionFr = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $funFact = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $funFactFr = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $traditions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $traditionsFr = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $virtues = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $virtuesFr = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $chakra = null;

    #[ORM\Column(length: 50)]
    private string $color = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lustre = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $origin = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $care = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $careFr = null;

    #[Vich\UploadableField(mapping: 'stone_images', fileNameProperty: 'imageName')]
    #[Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg', 'image/png', 'image/webp'])]
    private ?File $imageFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageName = null;

    #[ORM\Column]
    private int $position = 0;

    /** @var Collection<int, Product> */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'stones')]
    private Collection $products;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->products = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->nameFr !== '' ? $this->nameFr : $this->name;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        if ($this->slug === '') {
            $this->slug = (string) (new AsciiSlugger())->slug($name)->lower();
        }

        return $this;
    }

    public function getNameFr(): string
    {
        return $this->nameFr;
    }

    public function setNameFr(string $nameFr): static
    {
        $this->nameFr = $nameFr;

        if ($this->slugFr === '') {
            $this->slugFr = (string) (new AsciiSlugger('fr'))->slug($nameFr)->lower();
        }

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSlugFr(): string
    {
        return $this->slugFr;
    }

    public function setSlugFr(string $slugFr): static
    {
        $this->slugFr = $slugFr;

        return $this;
    }

    public function getShortDescription(): string
    {
        return $this->shortDescription;
    }

    public function setShortDescription(string $shortDescription): static
    {
        $this->shortDescription = $shortDescription;

        return $this;
    }

    public function getShortDescriptionFr(): string
    {
        return $this->shortDescriptionFr;
    }

    public function setShortDescriptionFr(string $shortDescriptionFr): static
    {
        $this->shortDescriptionFr = $shortDescriptionFr;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescriptionFr(): string
    {
        return $this->descriptionFr;
    }

    public function setDescriptionFr(string $descriptionFr): static
    {
        $this->descriptionFr = $descriptionFr;

        return $this;
    }

    public function getFunFact(): ?string
    {
        return $this->funFact;
    }

    public function setFunFact(?string $funFact): static
    {
        $this->funFact = $funFact;

        return $this;
    }

    public function getFunFactFr(): ?string
    {
        return $this->funFactFr;
    }

    public function setFunFactFr(?string $funFactFr): static
    {
        $this->funFactFr = $funFactFr;

        return $this;
    }

    public function getTraditions(): ?string
    {
        return $this->traditions;
    }

    public function setTraditions(?string $traditions): static
    {
        $this->traditions = $traditions;

        return $this;
    }

    public function getTraditionsFr(): ?string
    {
        return $this->traditionsFr;
    }

    public function setTraditionsFr(?string $traditionsFr): static
    {
        $this->traditionsFr = $traditionsFr;

        return $this;
    }

    public function getVirtues(): string
    {
        return $this->virtues;
    }

    public function setVirtues(string $virtues): static
    {
        $this->virtues = $virtues;

        return $this;
    }

    public function getVirtuesFr(): string
    {
        return $this->virtuesFr;
    }

    public function setVirtuesFr(string $virtuesFr): static
    {
        $this->virtuesFr = $virtuesFr;

        return $this;
    }

    public function getChakra(): ?string
    {
        return $this->chakra;
    }

    public function setChakra(?string $chakra): static
    {
        $this->chakra = $chakra;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): static
    {
        $this->color = $color;

        return $this;
    }

    public function getLustre(): ?string
    {
        return $this->lustre;
    }

    public function setLustre(?string $lustre): static
    {
        $this->lustre = $lustre;

        return $this;
    }

    public function getOrigin(): ?string
    {
        return $this->origin;
    }

    public function setOrigin(?string $origin): static
    {
        $this->origin = $origin;

        return $this;
    }

    public function getCare(): ?string
    {
        return $this->care;
    }

    public function setCare(?string $care): static
    {
        $this->care = $care;

        return $this;
    }

    public function getCareFr(): ?string
    {
        return $this->careFr;
    }

    public function setCareFr(?string $careFr): static
    {
        $this->careFr = $careFr;

        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): static
    {
        $this->imageFile = $imageFile;

        if ($imageFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): static
    {
        $this->imageName = $imageName;

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

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->addStone($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            $product->removeStone($this);
        }

        return $this;
    }

    public function getAvailableProductCount(): int
    {
        return $this->products->filter(
            static fn (Product $p): bool => $p->isVisibleInCatalog() && !$p->isSoldOut(),
        )->count();
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
