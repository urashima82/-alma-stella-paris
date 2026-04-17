<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\GeneratedVisual;
use App\Enum\VisualStatus;
use App\Enum\VisualType;
use App\Enum\VisualWorkflowStatus;
use App\Message\GenerateVisualMessage;
use App\Repository\GeneratedVisualRepository;
use App\Service\Visual\VisualApprovalHandler;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

/** @extends AbstractCrudController<GeneratedVisual> */
class GeneratedVisualCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly GeneratedVisualRepository $generatedVisualRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly VisualApprovalHandler $visualApprovalHandler,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return GeneratedVisual::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Visuel généré')
            ->setEntityLabelInPlural('Visuels générés')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['product.name', 'product.nameFr'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $approve = Action::new('approveVisual', 'Approuver', 'fa fa-check')
            ->linkToCrudAction('approveVisual')
            ->setCssClass('btn btn-success btn-sm')
            ->displayIf(static fn (GeneratedVisual $v): bool => $v->getStatus() === VisualStatus::PendingReview);

        $reject = Action::new('rejectVisual', 'Rejeter', 'fa fa-times')
            ->linkToCrudAction('rejectVisual')
            ->setCssClass('btn btn-danger btn-sm')
            ->displayIf(static fn (GeneratedVisual $v): bool => $v->getStatus() === VisualStatus::PendingReview);

        $regenerate = Action::new('regenerateVisual', 'Regénérer', 'fa fa-sync')
            ->linkToCrudAction('regenerateVisual')
            ->setCssClass('btn btn-warning btn-sm')
            ->displayIf(static fn (GeneratedVisual $v): bool => \in_array($v->getStatus(), [VisualStatus::Rejected, VisualStatus::Failed], true));

        return $actions
            ->add(Crud::PAGE_INDEX, $approve)
            ->add(Crud::PAGE_INDEX, $reject)
            ->add(Crud::PAGE_INDEX, $regenerate)
            ->add(Crud::PAGE_DETAIL, $approve)
            ->add(Crud::PAGE_DETAIL, $reject)
            ->add(Crud::PAGE_DETAIL, $regenerate)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('product')
            ->add('type')
            ->add('status');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield AssociationField::new('product', 'Produit');

        yield ChoiceField::new('type', 'Type')
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

        yield IntegerField::new('variant', 'Variante');

        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                VisualStatus::Generating->label() => VisualStatus::Generating,
                VisualStatus::PendingReview->label() => VisualStatus::PendingReview,
                VisualStatus::Approved->label() => VisualStatus::Approved,
                VisualStatus::Rejected->label() => VisualStatus::Rejected,
                VisualStatus::Failed->label() => VisualStatus::Failed,
            ])
            ->renderAsBadges([
                VisualStatus::Generating->value => 'primary',
                VisualStatus::PendingReview->value => 'warning',
                VisualStatus::Approved->value => 'success',
                VisualStatus::Rejected->value => 'danger',
                VisualStatus::Failed->value => 'danger',
            ]);

        yield ImageField::new('path', 'Aperçu')
            ->setBasePath('/storage/products')
            ->onlyOnIndex();

        yield IntegerField::new('categoryPromptVersion', 'Version prompt')
            ->onlyOnDetail();

        yield TextareaField::new('promptUsed', 'Prompt utilisé')
            ->onlyOnDetail()
            ->setNumOfRows(10);

        yield TextField::new('geminiRequestId', 'Request ID Gemini')
            ->onlyOnDetail();

        yield TextareaField::new('errorMessage', 'Erreur')
            ->onlyOnDetail();

        yield DateTimeField::new('createdAt', 'Généré le')
            ->setFormat('dd/MM/yyyy HH:mm');
    }

    /** @param AdminContext<GeneratedVisual> $context */
    #[AdminRoute]
    public function approveVisual(AdminContext $context): Response
    {
        /** @var GeneratedVisual $visual */
        $visual = $context->getEntity()->getInstance();
        $visual->setStatus(VisualStatus::Approved);

        $this->visualApprovalHandler->copyToProductImages($visual);

        $product = $visual->getProduct();
        if ($product !== null && $this->generatedVisualRepository->hasApprovedForAllTypes($product)) {
            $product->setVisualStatus(VisualWorkflowStatus::ReadyForReview);
        }

        $this->entityManager->flush();

        $this->addFlash('success', \sprintf('Visuel %s v%d approuvé et copié vers les images produit.', $visual->getType()->label(), $visual->getVariant()));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }

    /** @param AdminContext<GeneratedVisual> $context */
    #[AdminRoute]
    public function rejectVisual(AdminContext $context): Response
    {
        /** @var GeneratedVisual $visual */
        $visual = $context->getEntity()->getInstance();
        $visual->setStatus(VisualStatus::Rejected);

        $this->entityManager->flush();

        $this->addFlash('info', \sprintf('Visuel %s v%d rejeté.', $visual->getType()->label(), $visual->getVariant()));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }

    /** @param AdminContext<GeneratedVisual> $context */
    #[AdminRoute]
    public function regenerateVisual(AdminContext $context): Response
    {
        /** @var GeneratedVisual $visual */
        $visual = $context->getEntity()->getInstance();
        $product = $visual->getProduct();

        if ($product === null) {
            $this->addFlash('danger', 'Produit introuvable pour ce visuel.');

            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->generateUrl()
            );
        }

        $visual->setStatus(VisualStatus::Generating);
        $visual->setErrorMessage(null);
        $this->entityManager->flush();

        $this->messageBus->dispatch(
            new GenerateVisualMessage($product->getId(), $visual->getType(), $visual->getVariant())
        );

        $this->addFlash('success', \sprintf(
            'Regénération lancée : %s v%d pour « %s ».',
            $visual->getType()->label(),
            $visual->getVariant(),
            $product->getNameFr() ?: $product->getName(),
        ));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->generateUrl()
        );
    }
}
