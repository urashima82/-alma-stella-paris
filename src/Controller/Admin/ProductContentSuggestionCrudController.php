<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ProductContentSuggestion;
use App\Enum\ContentSuggestionStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Read-only listing of AI content suggestions (M17).
 *
 * Approve / Reject / Regenerate live in the inline workspace on the Product
 * edit page. This CRUD is the historical view: every suggestion ever produced,
 * its status, and the context snapshot the AI was given.
 *
 * @extends AbstractCrudController<ProductContentSuggestion>
 */
class ProductContentSuggestionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductContentSuggestion::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Suggestion de contenu')
            ->setEntityLabelInPlural('Suggestions de contenu')
            ->setDefaultSort(['generatedAt' => 'DESC'])
            ->setSearchFields(['product.name', 'product.nameFr', 'nameEn', 'nameFr'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('product')
            ->add('status');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('product', 'Produit');

        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                ContentSuggestionStatus::Generating->label() => ContentSuggestionStatus::Generating,
                ContentSuggestionStatus::Pending->label() => ContentSuggestionStatus::Pending,
                ContentSuggestionStatus::Approved->label() => ContentSuggestionStatus::Approved,
                ContentSuggestionStatus::Rejected->label() => ContentSuggestionStatus::Rejected,
                ContentSuggestionStatus::Applied->label() => ContentSuggestionStatus::Applied,
            ])
            ->renderAsBadges([
                ContentSuggestionStatus::Generating->value => 'primary',
                ContentSuggestionStatus::Pending->value => 'warning',
                ContentSuggestionStatus::Approved->value => 'success',
                ContentSuggestionStatus::Rejected->value => 'danger',
                ContentSuggestionStatus::Applied->value => 'info',
            ]);

        yield TextField::new('nameFr', 'Nom (FR)')->onlyOnIndex();
        yield TextField::new('nameEn', 'Nom (EN)')->onlyOnIndex();

        yield TextField::new('nameFr', 'Nom (FR)')->onlyOnDetail();
        yield TextField::new('nameEn', 'Nom (EN)')->onlyOnDetail();
        yield TextareaField::new('descriptionFr', 'Description (FR)')->onlyOnDetail()->setNumOfRows(6);
        yield TextareaField::new('descriptionEn', 'Description (EN)')->onlyOnDetail()->setNumOfRows(6);

        yield TextField::new('modelUsed', 'Modèle')->onlyOnDetail();
        yield TextField::new('requestId', 'Request ID')->onlyOnDetail();
        yield TextareaField::new('additionalContext', 'Contexte additionnel')->onlyOnDetail();
        yield TextareaField::new('errorMessage', 'Erreur')->onlyOnDetail();

        yield DateTimeField::new('generatedAt', 'Généré le')
            ->setFormat('dd/MM/yyyy HH:mm');
        yield DateTimeField::new('appliedAt', 'Appliqué le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->onlyOnDetail();
    }
}
