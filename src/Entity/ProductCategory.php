<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\VisualType;
use App\Repository\ProductCategoryRepository;
use App\Validator\MaxCategoryDepth;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ORM\Entity(repositoryClass: ProductCategoryRepository::class)]
#[MaxCategoryDepth]
class ProductCategory
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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descriptionFr = null;

    #[Gedmo\SortablePosition]
    #[ORM\Column]
    private int $position = 0;

    #[Gedmo\SortableGroup]
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $children;

    /** @var Collection<int, Product> */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'category')]
    private Collection $products;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $preservationInstructions = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specificFocus = null;

    /** @var Collection<int, CategoryVisualPrompt> */
    #[ORM\OneToMany(targetEntity: CategoryVisualPrompt::class, mappedBy: 'category', cascade: ['persist'])]
    private Collection $visualPrompts;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->products = new ArrayCollection();
        $this->visualPrompts = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->parent !== null
            ? $this->parent->getName().' → '.$this->name
            : $this->name;
    }

    public function getTreeLabel(): string
    {
        return $this->name;
    }

    /**
     * Sortable key that groups children under their parent.
     * Format: "PPP.CCC" where PPP = parent position, CCC = child position.
     * Roots: "PPP.---" (sorts before any child at same parent position).
     */
    public function getTreeSortKey(): string
    {
        if ($this->parent !== null) {
            return \sprintf('%03d.%03d', $this->parent->getPosition(), $this->position);
        }

        return \sprintf('%03d.---', $this->position);
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDescriptionFr(): ?string
    {
        return $this->descriptionFr;
    }

    public function setDescriptionFr(?string $descriptionFr): static
    {
        $this->descriptionFr = $descriptionFr;

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

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /** @return Collection<int, self> */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function hasChildren(): bool
    {
        return !$this->children->isEmpty();
    }

    public function isLeaf(): bool
    {
        return $this->children->isEmpty();
    }

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    public function getPreservationInstructions(): ?string
    {
        return $this->preservationInstructions;
    }

    public function setPreservationInstructions(?string $preservationInstructions): static
    {
        $this->preservationInstructions = $preservationInstructions;

        return $this;
    }

    public function getSpecificFocus(): ?string
    {
        return $this->specificFocus;
    }

    public function setSpecificFocus(?string $specificFocus): static
    {
        $this->specificFocus = $specificFocus;

        return $this;
    }

    /** @return Collection<int, CategoryVisualPrompt> */
    public function getVisualPrompts(): Collection
    {
        return $this->visualPrompts;
    }

    public function addVisualPrompt(CategoryVisualPrompt $prompt): static
    {
        if (!$this->visualPrompts->contains($prompt)) {
            $this->visualPrompts->add($prompt);
            $prompt->setCategory($this);
        }

        return $this;
    }

    public function removeVisualPrompt(CategoryVisualPrompt $prompt): static
    {
        if ($this->visualPrompts->removeElement($prompt)) {
            if ($prompt->getCategory() === $this) {
                $prompt->setCategory(null);
            }
        }

        return $this;
    }

    public function getVisualPromptFor(VisualType $type): ?CategoryVisualPrompt
    {
        foreach ($this->visualPrompts as $prompt) {
            if ($prompt->getVisualType() === $type && $prompt->isActive()) {
                return $prompt;
            }
        }

        return null;
    }

    public function hasVisualPromptFor(VisualType $type): bool
    {
        return $this->getVisualPromptFor($type) !== null;
    }

    public function getPublishedProductCount(): int
    {
        return $this->products->filter(
            static fn (Product $p): bool => $p->isPublished(),
        )->count();
    }

    /**
     * Total published product count including all children (for parent categories).
     */
    public function getTotalPublishedProductCount(): int
    {
        $count = $this->getPublishedProductCount();

        foreach ($this->children as $child) {
            $count += $child->getPublishedProductCount();
        }

        return $count;
    }
}
