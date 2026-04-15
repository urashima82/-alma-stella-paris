<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProductCategoryRepository;
use App\Validator\MaxCategoryDepth;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->products = new ArrayCollection();
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
