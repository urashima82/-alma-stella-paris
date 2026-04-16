<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Stone;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

/** @extends AbstractCrudController<Stone> */
class StoneCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Stone::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Pierre')
            ->setEntityLabelInPlural('Pierres')
            ->setSearchFields(['name', 'nameFr', 'slug', 'color'])
            ->setDefaultSort(['position' => 'ASC']);
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield IdField::new('id');
            yield TextField::new('nameFr', 'Nom');
            yield TextField::new('color', 'Couleur');
            yield TextField::new('chakra', 'Chakra');
            yield IntegerField::new('availableProductCount', 'Produits')
                ->setTemplatePath('admin/field/stone_product_count.html.twig');
            yield IntegerField::new('position', 'Position');

            return;
        }

        // ── Form layout ──
        yield FormField::addColumn(8);

        yield FormField::addFieldset('Identité', 'fa fa-gem');
        yield TextField::new('name', 'Nom (EN)');
        yield TextField::new('nameFr', 'Nom (FR)');
        yield SlugField::new('slug')->setTargetFieldName('name');
        yield SlugField::new('slugFr', 'Slug (FR)')->setTargetFieldName('nameFr');

        yield FormField::addFieldset('Accroche', 'fa fa-quote-left');
        yield TextField::new('shortDescription', 'Accroche (EN)')
            ->setHelp('Courte phrase pour les badges et tooltips.');
        yield TextField::new('shortDescriptionFr', 'Accroche (FR)');

        yield FormField::addFieldset('Description complète', 'fa fa-align-left');
        yield TextareaField::new('description', 'Description (EN)')
            ->setNumOfRows(6);
        yield TextareaField::new('descriptionFr', 'Description (FR)')
            ->setNumOfRows(6);

        yield FormField::addFieldset('Le saviez-vous ?', 'fa fa-lightbulb');
        yield TextareaField::new('funFact', 'Fun fact (EN)')
            ->setNumOfRows(3)
            ->setRequired(false);
        yield TextareaField::new('funFactFr', 'Le saviez-vous ? (FR)')
            ->setNumOfRows(3)
            ->setRequired(false);

        yield FormField::addFieldset('Traditions', 'fa fa-scroll');
        yield TextareaField::new('traditions', 'Traditions (EN)')
            ->setNumOfRows(3)
            ->setRequired(false);
        yield TextareaField::new('traditionsFr', 'Traditions (FR)')
            ->setNumOfRows(3)
            ->setRequired(false);

        yield FormField::addFieldset('Vertus', 'fa fa-heart');
        yield TextareaField::new('virtues', 'Vertus (EN)')
            ->setNumOfRows(4);
        yield TextareaField::new('virtuesFr', 'Vertus (FR)')
            ->setNumOfRows(4);

        yield FormField::addFieldset('Entretien', 'fa fa-hand-sparkles');
        yield TextareaField::new('care', 'Entretien (EN)')
            ->setNumOfRows(3)
            ->setRequired(false);
        yield TextareaField::new('careFr', 'Entretien (FR)')
            ->setNumOfRows(3)
            ->setRequired(false);

        // ── Right column ──
        yield FormField::addColumn(4);

        yield FormField::addFieldset('Caractéristiques', 'fa fa-info-circle');
        yield TextField::new('color', 'Couleur dominante');
        yield TextField::new('lustre', 'Éclat')
            ->setRequired(false);
        yield TextField::new('origin', 'Origine')
            ->setRequired(false);
        yield TextField::new('chakra', 'Chakra(s)')
            ->setRequired(false)
            ->setHelp('Ex: Cœur, Racine');

        yield FormField::addFieldset('Image', 'fa fa-camera');
        yield TextField::new('imageFile', 'Photo de la pierre')
            ->setFormType(VichImageType::class)
            ->setRequired(false)
            ->setFormTypeOption('row_attr', [
                'class' => 'field-image-crop',
                'data-crop-ratio' => '1/1',
                'data-crop-max-width' => '800',
                'data-crop-max-height' => '800',
            ]);

        yield FormField::addFieldset('Ordre', 'fa fa-sort');
        yield IntegerField::new('position', 'Position');

        yield FormField::addFieldset('Produits liés', 'fa fa-link')
            ->collapsible();
        yield AssociationField::new('products', 'Produits')
            ->setFormTypeOption('by_reference', false);
    }
}
