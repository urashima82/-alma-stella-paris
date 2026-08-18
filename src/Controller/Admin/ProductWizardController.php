<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\GeneratedVisual;
use App\Entity\Product;
use App\Entity\ProductContentSuggestion;
use App\Entity\SourcePhoto;
use App\Enum\ContentSuggestionStatus;
use App\Enum\PhotoAngle;
use App\Enum\VisualStatus;
use App\Enum\VisualType;
use App\Enum\VisualWorkflowStatus;
use App\Form\Admin\ProductWizardData;
use App\Form\Admin\ProductWizardPhotoData;
use App\Form\Admin\ProductWizardType;
use App\Message\FillProductContentMessage;
use App\Message\GenerateVisualMessage;
use App\Repository\ProductRepository;
use App\Service\AiGenerationDispatcher;
use App\Service\Visual\ImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ProductWizardController extends AbstractController
{
    private const array DEFAULT_ANGLES = [
        PhotoAngle::Front,
        PhotoAngle::ThreeQuarter,
        PhotoAngle::Detail,
        PhotoAngle::Back,
    ];

    private const string DRAFT_SLUG_PREFIX = 'draft-';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageStorage $imageStorage,
        private readonly AiGenerationDispatcher $aiDispatcher,
        private readonly ProductRepository $productRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly AdminContextFactory $adminContextFactory,
        private readonly DashboardController $dashboardController,
    ) {
    }

    /**
     * The wizard renders templates that extend `@EasyAdmin/page/content.html.twig`,
     * which expects an `AdminContext` (sidebar, i18n, locale, asset paths) on the
     * request. As a non-CRUD controller, we are outside the AdminRouterSubscriber
     * flow, so we build and attach that context manually here.
     */
    private function attachAdminContext(Request $request): void
    {
        if ($request->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE) !== null) {
            return;
        }

        $context = $this->adminContextFactory->create($request, $this->dashboardController, null);
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);
    }

    #[Route('/admin/product/wizard/new', name: 'admin_product_wizard_new', methods: ['GET'])]
    public function new(Request $request): Response
    {
        $this->attachAdminContext($request);

        $data = $this->buildSeededData();
        $form = $this->createForm(ProductWizardType::class, $data);

        return $this->render('admin/product/wizard_form.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/admin/product/wizard/create', name: 'admin_product_wizard_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->attachAdminContext($request);

        $data = $this->buildSeededData();
        $form = $this->createForm(ProductWizardType::class, $data);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->render('admin/product/wizard_form.html.twig', [
                'form' => $form,
            ], new Response(null, $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
        }

        $product = $this->createDraftProduct($data);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $this->persistSourcePhotos($product, $data->getUploadedPhotos());

        $suggestion = $this->createContentSuggestion($product);
        $this->entityManager->flush();

        $this->aiDispatcher->dispatchContentFill($suggestion, new FillProductContentMessage(
            (int) $product->getId(),
            $suggestion->getId(),
        ));

        if ($data->generateVisuals) {
            $this->createAndDispatchVisuals($product);
            $product->setVisualStatus(VisualWorkflowStatus::PendingVisuals);
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('admin_product_wizard_wait', [
            'productId' => $product->getId(),
        ]);
    }

    #[Route('/admin/product/wizard/wait/{productId}', name: 'admin_product_wizard_wait', methods: ['GET'], requirements: ['productId' => '\d+'])]
    public function wait(Request $request, int $productId): Response
    {
        $this->attachAdminContext($request);

        $product = $this->loadDraftOrFail($productId);

        return $this->render('admin/product/wizard_wait.html.twig', [
            'product' => $product,
            'visualsRequested' => $product->getGeneratedVisuals()->count() > 0,
            'statusUrl' => $this->generateUrl('admin_product_wizard_status', ['productId' => $productId]),
            'cancelUrl' => $this->generateUrl('admin_product_wizard_cancel', ['productId' => $productId]),
            'editUrl' => $this->buildProductEditUrl($product),
        ]);
    }

    #[Route('/admin/product/wizard/wait/{productId}/status', name: 'admin_product_wizard_status', methods: ['GET'], requirements: ['productId' => '\d+'])]
    public function status(int $productId): JsonResponse
    {
        $product = $this->loadDraftOrFail($productId);

        $latestSuggestion = $this->getLatestSuggestion($product);
        $contentReady = $latestSuggestion !== null && \in_array(
            $latestSuggestion->getStatus(),
            [ContentSuggestionStatus::Pending, ContentSuggestionStatus::Applied],
            true,
        );
        $errorMessage = $latestSuggestion?->getStatus() === ContentSuggestionStatus::Rejected
            ? $latestSuggestion->getErrorMessage()
            : null;

        $visualsRequested = $product->getGeneratedVisuals()->count() > 0;
        $visualsReady = $visualsRequested && $product->getGeneratedVisuals()
            ->forAll(static fn (int $_k, GeneratedVisual $v): bool => $v->getStatus() !== VisualStatus::Generating);

        return new JsonResponse([
            'contentReady' => $contentReady,
            'errorMessage' => $errorMessage,
            'visualsRequested' => $visualsRequested,
            'visualsReady' => $visualsRequested ? $visualsReady : null,
        ]);
    }

    #[Route('/admin/product/wizard/cancel/{productId}', name: 'admin_product_wizard_cancel', methods: ['POST'], requirements: ['productId' => '\d+'])]
    public function cancel(int $productId): Response
    {
        $product = $this->loadDraftOrFail($productId);

        // Delete physical files first; cascade removal handles the entity rows.
        foreach ($product->getSourcePhotos() as $source) {
            $this->imageStorage->delete($source->getPath());
        }
        foreach ($product->getGeneratedVisuals() as $visual) {
            if ($visual->getPath() !== '') {
                $this->imageStorage->delete($visual->getPath());
            }
        }

        $this->entityManager->remove($product);
        $this->entityManager->flush();

        $this->addFlash('info', 'Génération annulée, le brouillon a été supprimé.');

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(ProductCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }

    private function loadDraftOrFail(int $productId): Product
    {
        $product = $this->productRepository->find($productId);
        if ($product === null) {
            throw $this->createNotFoundException('Brouillon introuvable.');
        }

        // Safety: only wizard-created drafts (slug starts with `draft-`) are
        // exposed via the wait/cancel endpoints — never operate on a real product.
        if (!\str_starts_with($product->getSlug(), self::DRAFT_SLUG_PREFIX)) {
            throw $this->createNotFoundException('Ce produit n\'est plus un brouillon.');
        }

        return $product;
    }

    private function getLatestSuggestion(Product $product): ?ProductContentSuggestion
    {
        // The collection is ordered by generatedAt DESC at the entity mapping level.
        return $product->getContentSuggestions()->first() ?: null;
    }

    private function buildProductEditUrl(Product $product): string
    {
        $url = $this->adminUrlGenerator
            ->setController(ProductCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($product->getId())
            ->generateUrl();

        return $url.'#tab-contenu-ia';
    }

    private function buildSeededData(): ProductWizardData
    {
        $data = new ProductWizardData();
        foreach (self::DEFAULT_ANGLES as $angle) {
            $photo = new ProductWizardPhotoData();
            $photo->angle = $angle;
            $data->photos->add($photo);
        }

        return $data;
    }

    private function createDraftProduct(ProductWizardData $data): Product
    {
        $draftSlug = self::DRAFT_SLUG_PREFIX.\uniqid('', true);

        $product = new Product();
        // Set slugs first so the auto-slug logic in setName/setNameFr leaves them alone.
        $product->setSlug($draftSlug);
        $product->setSlugFr($draftSlug);
        $product->setName('Nouveau produit (en cours…)');
        $product->setNameFr('Nouveau produit (en cours…)');
        $product->setDescription('(génération en cours)');
        $product->setDescriptionFr('(génération en cours)');

        $product->setCategory($data->category);
        foreach ($data->stones as $stone) {
            $product->addStone($stone);
        }
        $product->setBasePrice((float) $data->basePrice);
        $product->setShippingTier($data->shippingTier);
        $product->setIsPublished($data->isPublished);

        return $product;
    }

    /**
     * @param ProductWizardPhotoData[] $photos
     */
    private function persistSourcePhotos(Product $product, array $photos): void
    {
        foreach ($photos as $index => $photo) {
            if ($photo->file === null) {
                continue;
            }

            $position = $index + 1;
            $sourcePhoto = new SourcePhoto();
            $sourcePhoto->setProduct($product);
            $sourcePhoto->setAngle($photo->angle);
            $sourcePhoto->setPosition($position);

            $path = $this->imageStorage->storeSourcePhoto($photo->file, $product, $position);
            $sourcePhoto->setPath($path);

            $this->entityManager->persist($sourcePhoto);
        }

        $this->entityManager->flush();
    }

    private function createContentSuggestion(Product $product): ProductContentSuggestion
    {
        $suggestion = new ProductContentSuggestion();
        $suggestion->setProduct($product);
        $suggestion->setStatus(ContentSuggestionStatus::Generating);
        $this->entityManager->persist($suggestion);

        return $suggestion;
    }

    private function createAndDispatchVisuals(Product $product): void
    {
        foreach (VisualType::cases() as $type) {
            $visual = new GeneratedVisual();
            $visual->setProduct($product);
            $visual->setType($type);
            $visual->setVariant(1);
            $visual->setStatus(VisualStatus::Generating);
            $this->entityManager->persist($visual);
            $this->entityManager->flush();

            $this->aiDispatcher->dispatchVisualGeneration($visual, new GenerateVisualMessage(
                (int) $product->getId(),
                $type,
                1,
                $visual->getId(),
            ));
        }
    }
}
