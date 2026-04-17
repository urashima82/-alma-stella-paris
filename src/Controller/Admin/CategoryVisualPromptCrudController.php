<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CategoryVisualPrompt;
use App\Enum\VisualType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<CategoryVisualPrompt> */
class CategoryVisualPromptCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CategoryVisualPrompt::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Prompt visuel')
            ->setEntityLabelInPlural('Prompts visuels')
            ->setDefaultSort(['category' => 'ASC', 'visualType' => 'ASC'])
            ->setSearchFields(['framing', 'staging']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action->setLabel('Ajouter un prompt'));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('category')
            ->add('active');
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
            yield IdField::new('id')->hideOnForm();
            yield AssociationField::new('category', 'Catégorie');
            yield ChoiceField::new('visualType', 'Type')
                ->setChoices([
                    VisualType::Vignette->label() => VisualType::Vignette,
                    VisualType::Worn->label() => VisualType::Worn,
                    VisualType::Lifestyle->label() => VisualType::Lifestyle,
                ])
                ->renderAsBadges([
                    VisualType::Vignette->value => 'info',
                    VisualType::Worn->value => 'primary',
                    VisualType::Lifestyle->value => 'warning',
                ]);
            yield IntegerField::new('version', 'Version');
            yield BooleanField::new('active', 'Actif');
            yield TextField::new('framing', 'Cadrage')
                ->setMaxLength(80);

            return;
        }

        // ── Form ──
        yield FormField::addFieldset('Identification', 'fa fa-tag');
        yield AssociationField::new('category', 'Catégorie')
            ->setRequired(true);
        yield ChoiceField::new('visualType', 'Type de visuel')
            ->setChoices([
                VisualType::Vignette->label() => VisualType::Vignette,
                VisualType::Worn->label() => VisualType::Worn,
                VisualType::Lifestyle->label() => VisualType::Lifestyle,
            ]);
        yield IntegerField::new('version', 'Version')
            ->setHelp('Incrémentez manuellement à chaque modification majeure du prompt.');
        yield BooleanField::new('active', 'Actif')
            ->setHelp('Seul le prompt actif de la version la plus récente est utilisé pour la génération.');

        yield FormField::addFieldset('Contenu du prompt', 'fa fa-pen');
        yield TextareaField::new('framing', 'Cadrage (EN)')
            ->setNumOfRows(4)
            ->setHelp('Instructions de cadrage en anglais. Ex : "close-up, ring shown from frontal 3/4 angle"');
        yield TextareaField::new('staging', 'Mise en scène (EN)')
            ->setNumOfRows(4)
            ->setHelp('Instructions de mise en scène en anglais. Ex : "floating on subtle shadow with soft professional lighting"');
        yield ArrayField::new('props', 'Éléments d\'ambiance')
            ->setHelp('Éléments de décor suggérés. Ex : marble surface, silk fabric, velvet cushion');
    }
}
