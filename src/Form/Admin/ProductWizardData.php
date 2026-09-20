<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\ProductCategory;
use App\Entity\SourcePhoto;
use App\Entity\Stone;
use App\Enum\ShippingTier;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ProductWizardData
{
    /**
     * @var Collection<int, ProductWizardPhotoData>
     */
    #[Assert\Valid]
    public Collection $photos;

    #[Assert\NotNull(message: 'La catégorie est obligatoire.')]
    public ?ProductCategory $category = null;

    /**
     * @var Collection<int, Stone>
     */
    public Collection $stones;

    #[Assert\NotNull(message: 'Le prix est obligatoire.')]
    #[Assert\Positive(message: 'Le prix doit être supérieur à zéro.')]
    public ?float $basePrice = null;

    #[Assert\NotNull(message: 'La tranche d\'expédition est obligatoire.')]
    public ShippingTier $shippingTier = ShippingTier::Standard;

    public bool $isPublished = false;

    public bool $generateVisuals = false;

    public function __construct()
    {
        $this->photos = new ArrayCollection();
        $this->stones = new ArrayCollection();
    }

    /**
     * @return ProductWizardPhotoData[]
     */
    public function getUploadedPhotos(): array
    {
        return \array_values(\array_filter(
            $this->photos->toArray(),
            static fn (ProductWizardPhotoData $p): bool => $p->file !== null,
        ));
    }

    /**
     * The photo tray builds its entries client-side, so the collection starts
     * empty and grows with whatever indices the browser submitted. `by_reference`
     * is false on the CollectionType, which routes new entries through these.
     */
    public function addPhoto(ProductWizardPhotoData $photo): void
    {
        if (!$this->photos->contains($photo)) {
            $this->photos->add($photo);
        }
    }

    public function removePhoto(ProductWizardPhotoData $photo): void
    {
        $this->photos->removeElement($photo);
    }

    #[Assert\Callback]
    public function validatePhotoCount(ExecutionContextInterface $context): void
    {
        $count = \count($this->getUploadedPhotos());

        if ($count < SourcePhoto::MIN_PER_PRODUCT) {
            $context->buildViolation(\sprintf(
                'Au moins %d photos sources sont requises.',
                SourcePhoto::MIN_PER_PRODUCT,
            ))->atPath('photos')->addViolation();
        } elseif ($count > SourcePhoto::MAX_PER_PRODUCT) {
            $context->buildViolation(\sprintf(
                'Maximum %d photos sources.',
                SourcePhoto::MAX_PER_PRODUCT,
            ))->atPath('photos')->addViolation();
        }
    }
}
