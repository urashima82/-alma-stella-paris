<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Filter\AvailableInFilter;
use App\Entity\Product;
use App\Enum\ShippingTier;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

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

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('category')
            ->add('isPublished')
            ->add('isSoldOut')
            ->add(AvailableInFilter::new('availableIn'));
    }

    public function configureFields(string $pageName): iterable
    {
        // ── Index only ──
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id');
            yield ImageField::new('thumbnail', 'Vignette')
                ->setBasePath('/uploads/products');
            yield TextField::new('name', 'Nom (EN)');
            yield NumberField::new('displayPrice', 'Prix affiché (USD)')
                ->setNumDecimals(2)
                ->formatValue(static fn (float $value): string => '$'.\number_format($value, 2));
            yield AssociationField::new('category', 'Catégorie');
            yield ChoiceField::new('availableIn', 'Disponible en')
                ->setChoices([
                    'France' => Product::COUNTRY_FRANCE,
                    'Mexique' => Product::COUNTRY_MEXICO,
                ])
                ->allowMultipleChoices()
                ->renderAsBadges([
                    Product::COUNTRY_FRANCE => 'primary',
                    Product::COUNTRY_MEXICO => 'success',
                ]);
            yield BooleanField::new('isPublished', 'Publié');
            yield BooleanField::new('isSoldOut', 'Vendu');
            yield DateTimeField::new('soldAt', 'Vendu le');
            yield DateTimeField::new('createdAt', 'Créé le');

            return;
        }

        // ══════════════════════════════════════════════
        //  Form layout — WordPress-style 2-column
        // ══════════════════════════════════════════════

        // ── Left column: main content ──
        yield FormField::addColumn(8);

        yield FormField::addFieldset('Identité', 'fa fa-pen-fancy');
        yield TextField::new('name', 'Nom (EN)');
        yield TextField::new('nameFr', 'Nom (FR)');
        yield SlugField::new('slug')->setTargetFieldName('name');
        yield SlugField::new('slugFr', 'Slug (FR)')->setTargetFieldName('nameFr');

        yield FormField::addFieldset('Descriptions', 'fa fa-align-left');
        yield TextareaField::new('description', 'Description (EN)')
            ->setNumOfRows(5);
        yield TextareaField::new('descriptionFr', 'Description (FR)')
            ->setNumOfRows(5);

        yield FormField::addFieldset('Photos', 'fa fa-camera');
        yield TextField::new('wornPhotoFile', 'Photo portée')
            ->setFormType(VichImageType::class)
            ->setHelp('Photo du bijou porté. Affichée en premier sur la page produit. Recadrage 4:5.')
            ->setFormTypeOption('row_attr', [
                'class' => 'field-image-crop',
                'data-crop-ratio' => '4/5',
                'data-crop-max-width' => '800',
                'data-crop-max-height' => '1000',
            ]);

        yield TextField::new('contextPhotoFile', 'Photo contexte')
            ->setFormType(VichImageType::class)
            ->setHelp('Photo d\'ambiance / lifestyle. Recadrage 4:5.')
            ->setFormTypeOption('row_attr', [
                'class' => 'field-image-crop',
                'data-crop-ratio' => '4/5',
                'data-crop-max-width' => '800',
                'data-crop-max-height' => '1000',
            ]);

        yield TextField::new('thumbnailFile', 'Vignette')
            ->setFormType(VichImageType::class)
            ->setHelp('Photo pour les cards catalogue et le panier. Recadrage 4:5.')
            ->setFormTypeOption('row_attr', [
                'class' => 'field-image-crop',
                'data-crop-ratio' => '4/5',
                'data-crop-max-width' => '600',
                'data-crop-max-height' => '750',
            ]);

        // ── Right column: sidebar ──
        yield FormField::addColumn(4);

        yield FormField::addFieldset('Publication', 'fa fa-eye');
        yield BooleanField::new('isPublished', 'Publié');
        yield BooleanField::new('isFeatured', 'Mis en avant');
        yield BooleanField::new('isSoldOut', 'Vendu');
        yield AssociationField::new('category', 'Catégorie');

        yield FormField::addFieldset('Disponibilité', 'fa fa-globe');
        yield ChoiceField::new('availableIn', 'Pays')
            ->setChoices([
                'France' => Product::COUNTRY_FRANCE,
                'Mexique' => Product::COUNTRY_MEXICO,
            ])
            ->allowMultipleChoices()
            ->renderExpanded()
            ->setHelp('Cochez les pays où ce produit est disponible.');

        yield FormField::addFieldset('Tarification', 'fa fa-tag');
        yield MoneyField::new('basePrice', 'Prix de base (USD)')
            ->setCurrency('USD')
            ->setStoredAsCents(false)
            ->setNumDecimals(2);
        yield MoneyField::new('compareAtPrice', 'Ancien prix (USD)')
            ->setCurrency('USD')
            ->setStoredAsCents(false)
            ->setNumDecimals(2)
            ->setRequired(false)
            ->setHelp('Laisser vide si pas de réduction. Le % sera calculé automatiquement.');
        yield ChoiceField::new('shippingTier', 'Tranche d\'expédition')
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

        yield FormField::addFieldset('À porter avec', 'fa fa-gem')
            ->collapsible();
        yield AssociationField::new('relatedProducts', 'Produits associés')
            ->setFormTypeOption('by_reference', false);

        yield FormField::addFieldset('Informations', 'fa fa-clock')
            ->collapsible()
            ->renderCollapsed();
        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormTypeOption('disabled', true);
        yield DateTimeField::new('updatedAt', 'Modifié le')
            ->setFormTypeOption('disabled', true);
    }
}
