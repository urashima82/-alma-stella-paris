<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Filter\AvailableInFilter;
use App\Entity\Product;
use App\Enum\ShippingTier;
use App\Enum\VisualType;
use App\Enum\VisualWorkflowStatus;
use App\Message\GenerateVisualMessage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
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
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

/** @extends AbstractCrudController<Product> */
class ProductCrudController extends AbstractCrudController
{
    private const int VARIANTS_PER_TYPE = 3;

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Produit')
            ->setEntityLabelInPlural('Produits')
            ->setSearchFields(['name', 'nameFr', 'slug', 'description', 'descriptionFr'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewOnSite = Action::new('viewOnSite', 'Voir sur le site', 'fa fa-external-link-alt')
            ->linkToUrl(static fn (Product $product): string => '/en/product/'.$product->getSlug())
            ->setHtmlAttributes(['target' => '_blank']);

        $generateVisuals = Action::new('generateVisuals', 'Générer les visuels', 'fa fa-wand-magic-sparkles')
            ->linkToCrudAction('generateVisuals')
            ->setCssClass('btn btn-warning btn-sm')
            ->displayIf(static fn (Product $p): bool => $p->getSourcePhotos()->count() > 0);

        $viewVisuals = Action::new('viewVisuals', 'Voir les visuels', 'fa fa-images')
            ->linkToCrudAction('viewVisuals')
            ->setCssClass('btn btn-info btn-sm')
            ->displayIf(static fn (Product $p): bool => $p->getGeneratedVisuals()->count() > 0);

        return $actions
            ->add(Crud::PAGE_INDEX, $viewOnSite)
            ->add(Crud::PAGE_EDIT, $viewOnSite)
            ->add(Crud::PAGE_INDEX, $generateVisuals)
            ->add(Crud::PAGE_INDEX, $viewVisuals)
            ->add(Crud::PAGE_EDIT, $generateVisuals)
            ->add(Crud::PAGE_EDIT, $viewVisuals);
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
        if ($pageName === Crud::PAGE_INDEX) {
            yield IdField::new('id');
            yield ImageField::new('thumbnail', 'Vignette')
                ->setBasePath('/uploads/products');
            yield TextField::new('nameFr', 'Nom');
            yield NumberField::new('displayPrice', 'Prix affiché (EUR)')
                ->setNumDecimals(2)
                ->formatValue(static fn (float $value): string => \number_format($value, 2).' €');
            yield TextField::new('category', 'Catégorie')
                ->formatValue(static fn ($value, Product $entity): string => (string) $entity->getCategory());
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
            yield BooleanField::new('isFeatured', 'Coup de cœur');
            yield BooleanField::new('isPublished', 'Publié');
            yield BooleanField::new('isSoldOut', 'Vendu');
            yield DateTimeField::new('soldAt', 'Vendu le')
                ->setFormat('dd/MM/yyyy HH:mm');

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
        yield AssociationField::new('category', 'Catégorie')
            ->setQueryBuilder(static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->addSelect('COALESCE(p.position, entity.position) AS HIDDEN sortPosition')
                ->leftJoin('entity.children', 'ch')
                ->leftJoin('entity.parent', 'p')
                ->groupBy('entity.id')
                ->having('COUNT(ch.id) = 0')
                ->orderBy('sortPosition', 'ASC')
                ->addOrderBy('entity.position', 'ASC')
            );

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
        yield MoneyField::new('basePrice', 'Prix de base (EUR)')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setNumDecimals(2);
        yield MoneyField::new('compareAtPrice', 'Ancien prix (EUR)')
            ->setCurrency('EUR')
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

        yield FormField::addFieldset('Pierres', 'fa fa-diamond')
            ->collapsible();
        yield AssociationField::new('stones', 'Pierres naturelles')
            ->setFormTypeOption('by_reference', false)
            ->setHelp('Sélectionnez les pierres présentes dans ce bijou.');

        yield FormField::addFieldset('À porter avec', 'fa fa-gem')
            ->collapsible();
        yield AssociationField::new('relatedProducts', 'Produits associés')
            ->setFormTypeOption('by_reference', false);

        yield FormField::addFieldset('Génération IA', 'fa fa-wand-magic-sparkles')
            ->collapsible();
        yield ChoiceField::new('visualStatus', 'Statut visuels')
            ->setChoices([
                VisualWorkflowStatus::Draft->label() => VisualWorkflowStatus::Draft,
                VisualWorkflowStatus::PendingVisuals->label() => VisualWorkflowStatus::PendingVisuals,
                VisualWorkflowStatus::ReadyForReview->label() => VisualWorkflowStatus::ReadyForReview,
                VisualWorkflowStatus::VisualsApproved->label() => VisualWorkflowStatus::VisualsApproved,
            ])
            ->renderAsBadges([
                VisualWorkflowStatus::Draft->value => 'secondary',
                VisualWorkflowStatus::PendingVisuals->value => 'primary',
                VisualWorkflowStatus::ReadyForReview->value => 'warning',
                VisualWorkflowStatus::VisualsApproved->value => 'success',
            ]);

        yield FormField::addFieldset('Informations', 'fa fa-clock')
            ->collapsible()
            ->renderCollapsed();
        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormTypeOption('disabled', true);
        yield DateTimeField::new('updatedAt', 'Modifié le')
            ->setFormTypeOption('disabled', true);
    }

    /** @param AdminContext<Product> $context */
    public function generateVisuals(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        if ($product->getSourcePhotos()->isEmpty()) {
            $this->addFlash('danger', 'Aucune photo source. Uploadez des photos avant de générer les visuels.');

            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl()
            );
        }

        $dispatched = 0;
        foreach (VisualType::cases() as $type) {
            for ($variant = 1; $variant <= self::VARIANTS_PER_TYPE; ++$variant) {
                $this->messageBus->dispatch(
                    new GenerateVisualMessage($product->getId(), $type, $variant)
                );
                ++$dispatched;
            }
        }

        $product->setVisualStatus(VisualWorkflowStatus::PendingVisuals);
        $this->entityManager->flush();

        $this->addFlash('success', \sprintf(
            '%d visuels en cours de génération pour « %s ».',
            $dispatched,
            $product->getNameFr() ?: $product->getName(),
        ));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }

    /** @param AdminContext<Product> $context */
    public function viewVisuals(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(GeneratedVisualCrudController::class)
                ->setAction(Action::INDEX)
                ->set('filters[product]', $product->getId())
                ->generateUrl()
        );
    }
}
