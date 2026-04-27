<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\Stone;
use App\Enum\ShippingTier;
use App\Repository\ProductCategoryRepository;
use App\Repository\StoneRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<ProductWizardData> */
class ProductWizardType extends AbstractType
{
    public function __construct(
        private readonly ProductCategoryRepository $categoryRepository,
        private readonly StoneRepository $stoneRepository,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('photos', CollectionType::class, [
                'entry_type' => ProductWizardPhotoType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'prototype_name' => '__photo_index__',
                'label' => false,
                'attr' => ['class' => 'wizard-photos'],
            ])
            ->add('category', EntityType::class, [
                'label' => 'Catégorie',
                'class' => ProductCategory::class,
                'choices' => $this->categoryRepository->findLeafCategories(),
                'choice_label' => static fn (ProductCategory $c): string => $c->getTreeLabelFr(),
                // Group leaves by their parent's French label for clean optgroups
                // (roots-without-children fall under a generic "Sans groupe" header).
                'group_by' => static fn (ProductCategory $c): string => $c->getParent() !== null
                    ? ($c->getParent()->getNameFr() !== '' ? $c->getParent()->getNameFr() : $c->getParent()->getName())
                    : 'Catégories principales',
                'placeholder' => '— Sélectionner —',
                'required' => true,
            ])
            ->add('stones', EntityType::class, [
                'label' => 'Pierres naturelles',
                'class' => Stone::class,
                'choices' => $this->stoneRepository->findBy([], ['nameFr' => 'ASC']),
                'choice_label' => static fn (Stone $s): string => $s->getNameFr(),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            ->add('basePrice', MoneyType::class, [
                'label' => 'Prix (EUR)',
                'currency' => 'EUR',
                'scale' => 2,
                'required' => true,
            ])
            ->add('shippingTier', EnumType::class, [
                'label' => 'Tranche d\'expédition',
                'class' => ShippingTier::class,
                'choice_label' => static fn (ShippingTier $tier): string => $tier->label(),
                'required' => true,
            ])
            ->add('availableIn', ChoiceType::class, [
                'label' => 'Disponible en',
                'choices' => [
                    'France' => Product::COUNTRY_FRANCE,
                    'Mexique' => Product::COUNTRY_MEXICO,
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => true,
            ])
            ->add('isPublished', CheckboxType::class, [
                'label' => 'Publier le produit immédiatement',
                'required' => false,
            ])
            ->add('generateVisuals', CheckboxType::class, [
                'label' => 'Générer aussi les visuels',
                'required' => false,
                'help' => 'Lance également la génération IA des 3 visuels (vignette, porté, lifestyle).',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductWizardData::class,
        ]);
    }
}
