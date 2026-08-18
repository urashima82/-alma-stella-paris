<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\GeneratedVisual;
use App\Entity\Product;
use App\Entity\ProductCategory;
use App\Entity\ProductContentSuggestion;
use App\Entity\SourcePhoto;
use App\Enum\ContentSuggestionStatus;
use App\Enum\PhotoAngle;
use App\Enum\ShippingTier;
use App\Enum\VisualStatus;
use App\Enum\VisualType;
use App\Enum\VisualWorkflowStatus;
use App\Message\FillProductContentMessage;
use App\Message\GenerateVisualMessage;
use App\Repository\GeneratedVisualRepository;
use App\Repository\ProductContentSuggestionRepository;
use App\Service\AiGenerationDispatcher;
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
use Symfony\Component\String\Slugger\AsciiSlugger;
use Twig\Environment;

/** @extends AbstractCrudController<Product> */
class ProductCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AiGenerationDispatcher $aiDispatcher,
        private readonly \App\Service\ShippingCostProvider $shippingCostProvider,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly GeneratedVisualRepository $generatedVisualRepository,
        private readonly VisualApprovalHandler $visualApprovalHandler,
        private readonly ImageStorage $imageStorage,
        private readonly ProductContentSuggestionRepository $contentSuggestionRepository,
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

        $newWithAi = Action::new('newWithAi', 'Nouveau (IA)', 'fa fa-wand-magic-sparkles')
            ->createAsGlobalAction()
            ->linkToUrl($this->generateUrl('admin_product_wizard_new'))
            ->setCssClass('btn btn-primary');

        return $actions
            ->add(Crud::PAGE_INDEX, $viewOnSite)
            ->add(Crud::PAGE_EDIT, $viewOnSite)
            ->add(Crud::PAGE_INDEX, $newWithAi);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('category')
            ->add('isPublished')
            ->add('isSoldOut');
    }

    public function configureFields(string $pageName): iterable
    {
        // ── Index only ──
        if ($pageName === Crud::PAGE_INDEX) {
            yield IdField::new('id');
            yield ImageField::new('thumbnail', 'Vignette')
                ->setBasePath('/uploads/products');
            yield TextField::new('nameFr', 'Nom');
            yield NumberField::new('basePrice', 'Prix affiché (EUR)')
                ->setNumDecimals(2)
                ->formatValue(fn ($value, Product $entity): string => \number_format(
                    $this->shippingCostProvider->getDisplayPrice($entity->getBasePrice(), $entity->getShippingTier()),
                    2,
                ).' €');
            yield TextField::new('category', 'Catégorie')
                ->formatValue(static fn ($value, Product $entity): string => (string) $entity->getCategory());
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
            )
            ->setFormTypeOption('choice_label', static fn (ProductCategory $c): string => $c->getTreeLabelFr())
            ->setFormTypeOption('group_by', static fn (ProductCategory $c): string => $c->getParent() !== null
                ? ($c->getParent()->getNameFr() !== '' ? $c->getParent()->getNameFr() : $c->getParent()->getName())
                : 'Catégories principales');

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

        // ══════════════════════════════════════════════
        //  Tab: Contenu IA — content workspace, independent from visuals
        // ══════════════════════════════════════════════
        yield FormField::addTab('Contenu IA', 'fa fa-pen-to-square');
        yield FormField::addFieldset('')
            ->setCssClass('ai-content-workspace-target')
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

        $responseParameters->set('ai_content_workspace', [
            'product' => $product,
            'sourcePhotosCount' => \count($sourcePhotos),
            'activeSuggestion' => $this->contentSuggestionRepository->findLatestActiveForProduct($product),
            'recentSuggestions' => $this->contentSuggestionRepository->findBy(
                ['product' => $product],
                ['generatedAt' => 'DESC'],
                10,
            ),
        ]);

        return $responseParameters;
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
            $this->aiDispatcher->dispatchVisualGeneration($visual, new GenerateVisualMessage(
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
        $this->aiDispatcher->dispatchVisualGeneration($visual, new GenerateVisualMessage(
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

        $this->aiDispatcher->dispatchVisualGeneration($visual, new GenerateVisualMessage(
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

    // ══════════════════════════════════════════════
    //  AI content workspace — independent from visuals (M17)
    // ══════════════════════════════════════════════

    /** @param AdminContext<Product> $context */
    #[AdminRoute(options: ['methods' => ['POST']])]
    public function inlineGenerateContent(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        if ($product->getSourcePhotos()->isEmpty()) {
            $this->addFlash('danger', 'Ajoutez au moins une photo source avant de générer le contenu.');

            return $this->inlineAjaxResponse($context, $product);
        }

        // Reject any prior active suggestion (Generating or Pending) to honour
        // the "one active suggestion at a time" UI invariant.
        $priorActive = $this->contentSuggestionRepository->findActiveForProduct($product);
        foreach ($priorActive as $prior) {
            $prior->setStatus(ContentSuggestionStatus::Rejected);
        }

        $suggestion = new ProductContentSuggestion();
        $suggestion->setProduct($product);
        $suggestion->setStatus(ContentSuggestionStatus::Generating);
        $this->entityManager->persist($suggestion);
        $this->entityManager->flush();

        $this->aiDispatcher->dispatchContentFill($suggestion, new FillProductContentMessage(
            $product->getId(),
            $suggestion->getId(),
        ));

        $this->addFlash('success', 'Génération de contenu lancée.');

        return $this->inlineAjaxResponse($context, $product);
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute(options: ['methods' => ['POST']])]
    public function inlineRegenerateContent(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        $suggestion = $this->resolveSuggestionForProduct($context, $product);
        if ($suggestion === null) {
            return $this->inlineAjaxResponse($context, $product);
        }

        $additionalContext = (string) $context->getRequest()->request->get('additionalContext', '');
        $additionalContext = \trim($additionalContext);

        $suggestion->setStatus(ContentSuggestionStatus::Rejected);

        $newSuggestion = new ProductContentSuggestion();
        $newSuggestion->setProduct($product);
        $newSuggestion->setStatus(ContentSuggestionStatus::Generating);
        $this->entityManager->persist($newSuggestion);
        $this->entityManager->flush();

        $this->aiDispatcher->dispatchContentFill($newSuggestion, new FillProductContentMessage(
            $product->getId(),
            $newSuggestion->getId(),
            $additionalContext !== '' ? $additionalContext : null,
        ));

        $this->addFlash('success', 'Régénération de contenu lancée.');

        return $this->inlineAjaxResponse($context, $product);
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute(options: ['methods' => ['POST']])]
    public function inlineUpdateContent(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        $suggestion = $this->resolveSuggestionForProduct($context, $product);
        if ($suggestion === null) {
            return $this->inlineAjaxResponse($context, $product);
        }

        if ($suggestion->getStatus() !== ContentSuggestionStatus::Pending) {
            return $this->inlineAjaxResponse($context, $product);
        }

        $request = $context->getRequest();
        $suggestion->setNameFr((string) $request->request->get('nameFr', ''));
        $suggestion->setNameEn((string) $request->request->get('nameEn', ''));
        $suggestion->setDescriptionFr((string) $request->request->get('descriptionFr', ''));
        $suggestion->setDescriptionEn((string) $request->request->get('descriptionEn', ''));

        $this->entityManager->flush();

        return $this->inlineAjaxResponse($context, $product);
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute(options: ['methods' => ['POST']])]
    public function inlineApproveContent(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        $suggestion = $this->resolveSuggestionForProduct($context, $product);
        if ($suggestion === null) {
            return $this->inlineAjaxResponse($context, $product);
        }

        if ($suggestion->getStatus() !== ContentSuggestionStatus::Pending) {
            $this->addFlash('warning', 'Cette suggestion ne peut plus être appliquée.');

            return $this->inlineAjaxResponse($context, $product);
        }

        if ($suggestion->getNameFr() !== null && $suggestion->getNameFr() !== '') {
            $product->setNameFr($suggestion->getNameFr());
        }
        if ($suggestion->getNameEn() !== null && $suggestion->getNameEn() !== '') {
            $product->setName($suggestion->getNameEn());
        }
        if ($suggestion->getDescriptionFr() !== null && $suggestion->getDescriptionFr() !== '') {
            $product->setDescriptionFr($suggestion->getDescriptionFr());
        }
        if ($suggestion->getDescriptionEn() !== null && $suggestion->getDescriptionEn() !== '') {
            $product->setDescription($suggestion->getDescriptionEn());
        }

        // Wizard-created drafts have placeholder slugs (`draft-…`) until a content
        // suggestion is approved — recompute them from the approved name so the
        // product gets a real, browseable URL.
        if (\str_starts_with($product->getSlug(), 'draft-')) {
            $product->setSlug((string) (new AsciiSlugger())->slug($product->getName())->lower());
        }
        if (\str_starts_with($product->getSlugFr(), 'draft-')) {
            $product->setSlugFr((string) (new AsciiSlugger('fr'))->slug($product->getNameFr())->lower());
        }

        $suggestion->setStatus(ContentSuggestionStatus::Applied);
        $suggestion->setAppliedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->addFlash('success', 'Contenu appliqué au produit.');

        return $this->inlineAjaxResponse($context, $product);
    }

    /** @param AdminContext<Product> $context */
    #[AdminRoute(options: ['methods' => ['POST']])]
    public function inlineRejectContent(AdminContext $context): Response
    {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        $suggestion = $this->resolveSuggestionForProduct($context, $product);
        if ($suggestion === null) {
            return $this->inlineAjaxResponse($context, $product);
        }

        if (\in_array($suggestion->getStatus(), [ContentSuggestionStatus::Applied, ContentSuggestionStatus::Rejected], true)) {
            return $this->inlineAjaxResponse($context, $product);
        }

        $suggestion->setStatus(ContentSuggestionStatus::Rejected);
        $this->entityManager->flush();

        $this->addFlash('info', 'Suggestion de contenu rejetée.');

        return $this->inlineAjaxResponse($context, $product);
    }

    /**
     * Polling endpoint for the AI content workspace — independent from aiStatus
     * (visual workspace) so the two pipelines never interfere.
     *
     * @param AdminContext<Product> $context
     */
    #[AdminRoute]
    public function aiContentStatus(
        AdminContext $context,
        Environment $twig,
    ): JsonResponse {
        /** @var Product $product */
        $product = $context->getEntity()->getInstance();

        return $this->json($this->buildAiContentStatusPayload($product, $twig));
    }

    /** @param AdminContext<Product> $context */
    private function resolveSuggestionForProduct(AdminContext $context, Product $product): ?ProductContentSuggestion
    {
        $suggestionId = (int) $context->getRequest()->query->get('suggestionId', 0);
        $suggestion = $this->entityManager->getRepository(ProductContentSuggestion::class)->find($suggestionId);
        if ($suggestion === null || $suggestion->getProduct() !== $product) {
            $this->addFlash('danger', 'Suggestion introuvable pour ce produit.');

            return null;
        }

        return $suggestion;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAiContentStatusPayload(Product $product, Environment $twig): array
    {
        $active = $this->contentSuggestionRepository->findLatestActiveForProduct($product);

        /** @var ProductContentSuggestion[] $recent */
        $recent = $this->contentSuggestionRepository->findBy(
            ['product' => $product],
            ['generatedAt' => 'DESC'],
            10,
        );

        $signature = [];
        foreach ($recent as $suggestion) {
            $signature[] = $suggestion->getId().':'.$suggestion->getStatus()->value;
        }

        $isGenerating = $active !== null && $active->getStatus() === ContentSuggestionStatus::Generating;

        $cardHtml = $twig->render('admin/product/_ai_content_card.html.twig', [
            'product_id' => $product->getId(),
            'suggestion' => $active,
            'is_generating' => $isGenerating,
        ]);

        $historyHtml = $twig->render('admin/product/_ai_content_history.html.twig', [
            'recent' => $recent,
        ]);

        return [
            'productId' => $product->getId(),
            'hasActive' => $active !== null,
            'isGenerating' => $isGenerating,
            'sourcePhotosCount' => $product->getSourcePhotos()->count(),
            'cardHtml' => $cardHtml,
            'historyHtml' => $historyHtml,
            'signature' => \implode('|', $signature).'#'.($isGenerating ? '1' : '0'),
            'pending' => $isGenerating,
        ];
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
