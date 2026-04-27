<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Filter\AvailableInFilter;
use App\Entity\GeneratedVisual;
use App\Entity\Product;
use App\Entity\SourcePhoto;
use App\Enum\PhotoAngle;
use App\Enum\ShippingTier;
use App\Enum\VisualStatus;
use App\Enum\VisualType;
use App\Enum\VisualWorkflowStatus;
use App\Message\GenerateVisualMessage;
use App\Repository\GeneratedVisualRepository;
use App\Service\Visual\ImageStorage;
use App\Service\Visual\VisualApprovalHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
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
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Twig\Environment;

/** @extends AbstractCrudController<Product> */
class ProductCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly GeneratedVisualRepository $generatedVisualRepository,
        private readonly VisualApprovalHandler $visualApprovalHandler,
        private readonly ImageStorage $imageStorage,
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
            // Tie-breaker on id: createdAt alone is not deterministic when many rows
            // share the same timestamp (bulk fixtures, scripted imports), which makes
            // LIMIT/OFFSET pagination return overlapping pages.
            ->setDefaultSort(['createdAt' => 'DESC', 'id' => 'DESC'])
            ->overrideTemplate('crud/edit', 'admin/product/edit.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $viewOnSite = Action::new('viewOnSite', 'Voir sur le site', 'fa fa-external-link-alt')
            ->linkToUrl(static fn (Product $product): string => '/en/product/'.$product->getSlug())
            ->setHtmlAttributes(['target' => '_blank']);

        $manageSourcePhotos = Action::new('manageSourcePhotos', 'Photos sources', 'fa fa-camera')
            ->linkToCrudAction('manageSourcePhotos')
            ->setCssClass('btn btn-secondary btn-sm');

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
            ->add(Crud::PAGE_INDEX, $manageSourcePhotos)
            ->add(Crud::PAGE_INDEX, $generateVisuals)
            ->add(Crud::PAGE_INDEX, $viewVisuals)
            // On the edit page, the inline AI workspace replaces these redirect-only buttons.
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
        //  Form layout — Tabs + WordPress-style 2-column
        // ══════════════════════════════════════════════

        yield FormField::addTab('Fiche produit', 'fa fa-pen-fancy');

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

        yield FormField::addFieldset('Informations', 'fa fa-clock')
            ->collapsible()
            ->renderCollapsed();
        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormTypeOption('disabled', true);
        yield DateTimeField::new('updatedAt', 'Modifié le')
            ->setFormTypeOption('disabled', true);

        // ══════════════════════════════════════════════
        //  Tab: Visuels IA — workspace is injected via JS into this tab pane
        // ══════════════════════════════════════════════
        yield FormField::addTab('Visuels IA', 'fa fa-wand-magic-sparkles');
        yield FormField::addFieldset('')
            ->setCssClass('ai-workspace-target')
            ->setHelp('');
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $pageName = $responseParameters->get('pageName');
        if ($pageName !== Crud::PAGE_EDIT) {
            return $responseParameters;
        }

        $entityDto = $responseParameters->get('entity');
        if ($entityDto === null) {
            return $responseParameters;
        }

        /** @var Product $product */
        $product = $entityDto->getInstance();
        if ($product->getId() === null) {
            return $responseParameters;
        }

        $sourcePhotos = $product->getSourcePhotos()->toArray();
        \usort($sourcePhotos, static fn (SourcePhoto $a, SourcePhoto $b): int => $a->getPosition() <=> $b->getPosition());

        $responseParameters->set('ai_workspace', [
            'product' => $product,
            'sourcePhotos' => $sourcePhotos,
            'groupedVisuals' => $this->generatedVisualRepository->findByProductGroupedByType($product),
            'visualTypes' => VisualType::cases(),
            'photoAngles' => PhotoAngle::cases(),
        ]);

        return $responseParameters;
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute]
    public function manageSourcePhotos(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(SourcePhotoCrudController::class)
                ->setAction(Action::INDEX)
                ->set('filters[product][comparison]', '=')
                ->set('filters[product][value]', $product->getId())
                ->generateUrl()
        );
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute]
    public function generateVisuals(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        if ($product->getSourcePhotos()->isEmpty()) {
            $this->addFlash('danger', 'Aucune photo source. Uploadez des photos avant de générer les visuels.');

            return $this->redirectToProductIndex();
        }

        if ($product->getVisualStatus() === VisualWorkflowStatus::PendingVisuals) {
            $this->addFlash('warning', 'Une génération est déjà en cours pour ce produit. Veuillez patienter.');

            return $this->redirectToProductIndex();
        }

        $dispatched = 0;
        foreach (VisualType::cases() as $type) {
            $visual = $this->createGeneratingVisual($product, $type);
            $this->messageBus->dispatch(new GenerateVisualMessage(
                $product->getId(),
                $type,
                $visual->getVariant(),
                $visual->getId(),
            ));
            ++$dispatched;
        }

        $product->setVisualStatus(VisualWorkflowStatus::PendingVisuals);
        $this->entityManager->flush();

        $this->addFlash('success', \sprintf(
            '%d visuels en cours de génération pour « %s ».',
            $dispatched,
            $product->getNameFr() ?: $product->getName(),
        ));

        return $this->redirectToProductIndex();
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute]
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

    // ══════════════════════════════════════════════
    //  Inline AI workspace actions (bound to /admin/product edit page)
    // ══════════════════════════════════════════════

    /** @param AdminContext<Product> $context */
    #[AdminRoute(options: ['methods' => ['POST']])]
    public function inlineUploadSource(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();
        $request = $context->getRequest();

        /** @var UploadedFile|null $file */
        $file = $request->files->get('file');
        if ($file === null) {
            $this->addFlash('danger', 'Aucune photo reçue.');

            return $this->inlineAjaxResponse($context, $product);
        }

        $angleValue = (string) $request->request->get('angle', PhotoAngle::Front->value);
        $angle = PhotoAngle::tryFrom($angleValue) ?? PhotoAngle::Front;

        $sourcePhoto = new SourcePhoto();
        $sourcePhoto->setProduct($product);
        $sourcePhoto->setAngle($angle);
        $sourcePhoto->setPosition($product->getSourcePhotos()->count() + 1);

        $path = $this->imageStorage->storeSourcePhoto($file, $product);
        $sourcePhoto->setPath($path);

        $this->entityManager->persist($sourcePhoto);
        $this->entityManager->flush();

        $this->addFlash('success', \sprintf('Photo source « %s » ajoutée.', $angle->label()));

        return $this->inlineAjaxResponse($context, $product);
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute(options: ['methods' => ['POST']])]
    public function inlineDeleteSource(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();
        $sourceId = (int) $context->getRequest()->query->get('sourceId', 0);

        $source = $this->entityManager->getRepository(SourcePhoto::class)->find($sourceId);
        if ($source === null || $source->getProduct() !== $product) {
            $this->addFlash('danger', 'Photo source introuvable.');

            return $this->inlineAjaxResponse($context, $product);
        }

        $this->imageStorage->delete($source->getPath());
        $this->entityManager->remove($source);
        $this->entityManager->flush();

        $this->addFlash('success', 'Photo source supprimée.');

        return $this->inlineAjaxResponse($context, $product);
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute(options: ['methods' => ['POST']])]
    public function inlineGenerateAll(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        if ($product->getSourcePhotos()->isEmpty()) {
            $this->addFlash('danger', 'Ajoutez au moins une photo source avant de générer.');

            return $this->inlineAjaxResponse($context, $product);
        }

        $dispatched = 0;
        foreach (VisualType::cases() as $type) {
            $visual = $this->createGeneratingVisual($product, $type);
            $this->messageBus->dispatch(new GenerateVisualMessage(
                $product->getId(),
                $type,
                $visual->getVariant(),
                $visual->getId(),
            ));
            ++$dispatched;
        }

        $product->setVisualStatus(VisualWorkflowStatus::PendingVisuals);
        $this->entityManager->flush();

        $this->addFlash('success', \sprintf('%d générations lancées (1 par type).', $dispatched));

        return $this->inlineAjaxResponse($context, $product);
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute(options: ['methods' => ['POST']])]
    public function inlineGenerateType(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();
        $request = $context->getRequest();

        $typeValue = (string) $request->query->get('type', '');
        $type = VisualType::tryFrom($typeValue);
        if ($type === null) {
            $this->addFlash('danger', 'Type de visuel invalide.');

            return $this->inlineAjaxResponse($context, $product);
        }

        if ($product->getSourcePhotos()->isEmpty()) {
            $this->addFlash('danger', 'Ajoutez au moins une photo source avant de générer.');

            return $this->inlineAjaxResponse($context, $product);
        }

        $visual = $this->createGeneratingVisual($product, $type);
        $nextVariant = $visual->getVariant();
        $this->messageBus->dispatch(new GenerateVisualMessage(
            $product->getId(),
            $type,
            $nextVariant,
            $visual->getId(),
        ));

        $product->setVisualStatus(VisualWorkflowStatus::PendingVisuals);
        $this->entityManager->flush();

        $this->addFlash('success', \sprintf('Génération %s v%d lancée.', $type->label(), (int) $nextVariant));

        return $this->inlineAjaxResponse($context, $product);
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute]
    public function inlineApprove(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();
        $visual = $this->resolveVisualForProduct($context, $product);
        if ($visual === null) {
            return $this->inlineAjaxResponse($context, $product);
        }

        $this->visualApprovalHandler->approve($visual);

        if ($this->generatedVisualRepository->hasApprovedForAllTypes($product)) {
            $product->setVisualStatus(VisualWorkflowStatus::VisualsApproved);
        }

        $this->entityManager->flush();

        $this->addFlash('success', \sprintf('Visuel %s v%d approuvé.', $visual->getType()->label(), $visual->getVariant()));

        return $this->inlineAjaxResponse($context, $product);
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute]
    public function inlineReject(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();
        $visual = $this->resolveVisualForProduct($context, $product);
        if ($visual === null) {
            return $this->inlineAjaxResponse($context, $product);
        }

        $visual->setStatus(VisualStatus::Rejected);
        $this->entityManager->flush();

        $this->addFlash('info', \sprintf('Visuel %s v%d rejeté.', $visual->getType()->label(), $visual->getVariant()));

        return $this->inlineAjaxResponse($context, $product);
    }

    /**
     * Marks a stuck visual (still in Generating after a long wait) as Failed so
     * the user can relaunch it with the Régénérer button. The handler may have
     * crashed silently (worker killed, OOM, network hang) and the row would
     * otherwise stay in Generating indefinitely.
     *
     * @param AdminContext<Product> $context
     */
    #[AdminRoute]
    public function inlineCancelGeneration(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();
        $visual = $this->resolveVisualForProduct($context, $product);
        if ($visual === null) {
            return $this->inlineAjaxResponse($context, $product);
        }

        if ($visual->getStatus() !== VisualStatus::Generating) {
            return $this->inlineAjaxResponse($context, $product);
        }

        $visual->setStatus(VisualStatus::Failed);
        $visual->setErrorMessage('Génération annulée par l\'utilisateur (jugée trop longue).');
        $this->entityManager->flush();

        $this->addFlash('info', \sprintf('Génération %s v%d annulée.', $visual->getType()->label(), $visual->getVariant()));

        return $this->inlineAjaxResponse($context, $product);
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute]
    public function inlineRegenerate(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();
        $visual = $this->resolveVisualForProduct($context, $product);
        if ($visual === null) {
            return $this->inlineAjaxResponse($context, $product);
        }

        $visual->setStatus(VisualStatus::Generating);
        $visual->setErrorMessage(null);

        // Hide siblings still in Failed for the same type — only the row we
        // just flipped to Generating should remain visible.
        $this->generatedVisualRepository->markFailedAsRejected($product, $visual->getType());

        $product->setVisualStatus(VisualWorkflowStatus::PendingVisuals);

        $this->entityManager->flush();

        $this->messageBus->dispatch(new GenerateVisualMessage(
            $product->getId(),
            $visual->getType(),
            $visual->getVariant(),
            $visual->getId(),
        ));

        $this->addFlash('success', \sprintf('Régénération %s v%d lancée.', $visual->getType()->label(), $visual->getVariant()));

        return $this->inlineAjaxResponse($context, $product);
    }

    /**
     * Pre-creates a GeneratedVisual entity in `Generating` status before
     * dispatching the message. Computing nextVariant + persisting in the
     * same transaction prevents two concurrent clicks from producing two
     * rows for the same (product, type, variant) tuple.
     */
    private function createGeneratingVisual(Product $product, VisualType $type): GeneratedVisual
    {
        // Past failures become irrelevant once we kick off a fresh attempt.
        $this->generatedVisualRepository->markFailedAsRejected($product, $type);

        $maxVariant = (int) $this->generatedVisualRepository
            ->createQueryBuilder('v')
            ->select('COALESCE(MAX(v.variant), 0)')
            ->andWhere('v.product = :p')
            ->andWhere('v.type = :t')
            ->setParameter('p', $product)
            ->setParameter('t', $type)
            ->getQuery()
            ->getSingleScalarResult();

        $visual = new GeneratedVisual();
        $visual->setProduct($product);
        $visual->setType($type);
        $visual->setVariant($maxVariant + 1);
        $visual->setStatus(VisualStatus::Generating);

        $this->entityManager->persist($visual);
        $this->entityManager->flush();

        return $visual;
    }

    /** @param AdminContext<Product> $context */
    private function resolveVisualForProduct(AdminContext $context, Product $product): ?GeneratedVisual
    {
        $visualId = (int) $context->getRequest()->query->get('visualId', 0);
        $visual = $this->entityManager->getRepository(GeneratedVisual::class)->find($visualId);
        if ($visual === null || $visual->getProduct() !== $product) {
            $this->addFlash('danger', 'Visuel introuvable pour ce produit.');

            return null;
        }

        return $visual;
    }

    /**
     * For AJAX-driven inline actions: returns 204 No Content if the request is XHR
     * (preventing fetch from following a 302 with POST → EA edit endpoint rejects
     * the second POST as MethodNotAllowed). Falls back to a normal redirect for
     * non-AJAX callers (legacy direct navigation, browser dev tools, etc.).
     *
     * @param AdminContext<Product> $context
     */
    private function inlineAjaxResponse(AdminContext $context, Product $product): Response
    {
        if ($context->getRequest()->isXmlHttpRequest()) {
            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($product->getId())
                ->generateUrl()
        );
    }

    private function redirectToProductIndex(): Response
    {
        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }

    /**
     * Polling endpoint — pure read of the AI workspace state for the JS poller.
     * Message consumption is handled exclusively by the Messenger worker
     * (cron `messenger:consume gemini_async` running every minute, both in DDEV
     * via the ddev-cron add-on and in production via O2Switch crontab).
     *
     * @param AdminContext<Product> $context
     */
    #[AdminRoute]
    public function aiStatus(
        AdminContext $context,
        Environment $twig,
    ): JsonResponse {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        return $this->json($this->buildAiStatusPayload($product, $twig));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAiStatusPayload(Product $product, Environment $twig): array
    {
        $freshVisuals = $this->generatedVisualRepository->findBy(
            ['product' => $product],
            ['id' => 'ASC'],
        );

        $visuals = [];
        $signature = [];
        $hasGenerating = false;
        $byType = [
            VisualType::Vignette->value => [],
            VisualType::Worn->value => [],
            VisualType::Lifestyle->value => [],
        ];

        foreach ($freshVisuals as $visual) {
            $typeKey = $visual->getType()->value;
            $statusKey = $visual->getStatus()->value;

            // Signature must cover every visual (even Rejected) so transitions
            // to Rejected — like the auto-reject of a previously published
            // visual on a new approval — still trigger a refresh on the next poll.
            $signature[] = $visual->getId().':'.$statusKey;

            if ($statusKey === 'generating') {
                $hasGenerating = true;
            }

            // Rejected visuals are hidden from the workspace UI.
            if ($statusKey === 'rejected') {
                continue;
            }

            $byType[$typeKey][] = $visual;

            $visuals[] = [
                'id' => $visual->getId(),
                'type' => $typeKey,
                'variant' => $visual->getVariant(),
                'status' => $statusKey,
                'path' => $visual->getPath(),
                'hasError' => $visual->getErrorMessage() !== null,
            ];
        }

        $productId = $product->getId();
        $html = [];
        foreach ($byType as $typeValue => $typeVisuals) {
            if (\count($typeVisuals) === 0) {
                $html[$typeValue] = '<div class="ai-visual-empty"><i class="fa fa-image"></i></div>';
                continue;
            }
            $rendered = '';
            foreach ($typeVisuals as $visual) {
                $rendered .= $twig->render('admin/product/_ai_visual_tile.html.twig', [
                    'visual' => $visual,
                    'product_id' => $productId,
                ]);
            }
            $html[$typeValue] = $rendered;
        }

        $workflowStatus = $product->getVisualStatus();

        return [
            'productId' => $productId,
            'visualStatus' => $workflowStatus->value,
            'visualStatusLabel' => $workflowStatus->label(),
            'visualStatusColor' => $workflowStatus->badgeColor(),
            'sourcePhotosCount' => $product->getSourcePhotos()->count(),
            'visuals' => $visuals,
            'counts' => [
                VisualType::Vignette->value => \count($byType[VisualType::Vignette->value]),
                VisualType::Worn->value => \count($byType[VisualType::Worn->value]),
                VisualType::Lifestyle->value => \count($byType[VisualType::Lifestyle->value]),
            ],
            'html' => $html,
            'signature' => \implode('|', $signature).'#'.$workflowStatus->value,
            // Broad: drives the JS polling loop (also true between dispatch and the worker pickup)
            'pending' => $hasGenerating || $workflowStatus->value === 'pending_visuals',
            // Strict: drives the disabled state on generate buttons (only while Gemini is actively running)
            'activelyGenerating' => $hasGenerating,
        ];
    }
}
