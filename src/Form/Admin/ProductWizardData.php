<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\ProductCategory;
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

    #[Assert\Callback]
    public function validatePhotoCount(ExecutionContextInterface $context): void
    {
        $count = \count($this->getUploadedPhotos());

        if ($count < 2) {
            $context->buildViolation('Au moins 2 photos sources sont requises.')
                ->atPath('photos')
                ->addViolation();
        } elseif ($count > 4) {
            $context->buildViolation('Maximum 4 photos sources.')
                ->atPath('photos')
                ->addViolation();
        }
    }
}
