<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Enum\ShippingTier;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<Product> */
class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Produit')
            ->setEntityLabelInPlural('Produits')
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield TextField::new('name', 'Name (EN)');
        yield TextField::new('nameFr', 'Nom (FR)')->hideOnIndex();
        yield SlugField::new('slug')->setTargetFieldName('name')->hideOnIndex();

        yield TextareaField::new('description', 'Description (EN)')->hideOnIndex();
        yield TextareaField::new('descriptionFr', 'Description (FR)')->hideOnIndex();

        yield MoneyField::new('basePrice', 'Base price (USD)')
            ->setCurrency('USD')
            ->setStoredAsCents(false)
            ->setNumDecimals(2);

        yield NumberField::new('displayPrice', 'Display price (USD)')
            ->setNumDecimals(2)
            ->formatValue(static fn (float $value): string => '$'.\number_format($value, 2))
            ->hideOnForm();

        yield ChoiceField::new('shippingTier', 'Shipping tier')
            ->setChoices([
                ShippingTier::Standard->label() => ShippingTier::Standard,
                ShippingTier::Heavy->label() => ShippingTier::Heavy,
                ShippingTier::Set->label() => ShippingTier::Set,
            ])
            ->renderAsBadges([
                ShippingTier::Standard->value => 'success',
                ShippingTier::Heavy->value => 'warning',
                ShippingTier::Set->value => 'info',
            ]);

        yield AssociationField::new('category', 'Catégorie');

        yield BooleanField::new('isPublished', 'Publié');
        yield BooleanField::new('isFeatured', 'Mis en avant')->hideOnIndex();
        yield BooleanField::new('isSoldOut', 'Vendu');
        yield DateTimeField::new('soldAt', 'Vendu le')->hideOnForm();

        yield AssociationField::new('relatedProducts', 'Wear it with')
            ->setFormTypeOption('by_reference', false)
            ->hideOnIndex();

        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Modifié le')->hideOnForm()->hideOnIndex();
    }
}
