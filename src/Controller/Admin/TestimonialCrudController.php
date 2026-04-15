<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Testimonial;
use App\Enum\TestimonialStatus;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\HttpFoundation\Response;

/** @extends AbstractCrudController<Testimonial> */
class TestimonialCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Testimonial::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Témoignage')
            ->setEntityLabelInPlural('Témoignages')
            ->setSearchFields(['email', 'firstName', 'city', 'text'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $approve = Action::new('approve', 'Approuver', 'fa fa-check')
            ->linkToCrudAction('approveAction')
            ->displayIf(static fn (Testimonial $t): bool => $t->getStatus() !== TestimonialStatus::Approved);

        $reject = Action::new('reject', 'Rejeter', 'fa fa-times')
            ->linkToCrudAction('rejectAction')
            ->displayIf(static fn (Testimonial $t): bool => $t->getStatus() !== TestimonialStatus::Rejected);

        $batchApprove = Action::new('batchApprove', 'Approuver', 'fa fa-check')
            ->linkToCrudAction('batchApproveAction')
            ->addCssClass('btn btn-outline-success');

        $batchReject = Action::new('batchReject', 'Rejeter', 'fa fa-times')
            ->linkToCrudAction('batchRejectAction')
            ->addCssClass('btn btn-outline-danger');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $approve)
            ->add(Crud::PAGE_INDEX, $reject)
            ->addBatchAction($batchApprove)
            ->addBatchAction($batchReject)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, static fn (Action $action) => $action->setLabel('Voir'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action) => $action->setLabel('Modérer'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action) => $action->setLabel('Supprimer'));
    }

    /**
     * @param AdminContext<Testimonial> $context
     */
    #[AdminRoute(name: 'approve')]
    public function approveAction(AdminContext $context, EntityManagerInterface $em): Response
    {
        /** @var Testimonial $testimonial */
        $testimonial = $context->getEntity()->getInstance();
        $testimonial->setStatus(TestimonialStatus::Approved);
        $em->flush();

        $this->addFlash('success', 'Témoignage approuvé.');

        return $this->redirect($this->getReferer($context));
    }

    /**
     * @param AdminContext<Testimonial> $context
     */
    #[AdminRoute(name: 'reject')]
    public function rejectAction(AdminContext $context, EntityManagerInterface $em): Response
    {
        /** @var Testimonial $testimonial */
        $testimonial = $context->getEntity()->getInstance();
        $testimonial->setStatus(TestimonialStatus::Rejected);
        $em->flush();

        $this->addFlash('success', 'Témoignage rejeté.');

        return $this->redirect($this->getReferer($context));
    }

    /**
     * @param AdminContext<Testimonial> $context
     */
    #[AdminRoute(name: 'batchApprove')]
    public function batchApproveAction(AdminContext $context, EntityManagerInterface $em): Response
    {
        $count = $this->updateBatchStatus($context, $em, TestimonialStatus::Approved);
        $this->addFlash('success', \sprintf('%d témoignage%s approuvé%s.', $count, $count > 1 ? 's' : '', $count > 1 ? 's' : ''));

        return $this->redirect($this->getReferer($context));
    }

    /**
     * @param AdminContext<Testimonial> $context
     */
    #[AdminRoute(name: 'batchReject')]
    public function batchRejectAction(AdminContext $context, EntityManagerInterface $em): Response
    {
        $count = $this->updateBatchStatus($context, $em, TestimonialStatus::Rejected);
        $this->addFlash('success', \sprintf('%d témoignage%s rejeté%s.', $count, $count > 1 ? 's' : '', $count > 1 ? 's' : ''));

        return $this->redirect($this->getReferer($context));
    }

    /**
     * @param AdminContext<Testimonial> $context
     */
    private function updateBatchStatus(AdminContext $context, EntityManagerInterface $em, TestimonialStatus $status): int
    {
        $ids = $context->getRequest()->request->all('batchActionEntityIds');
        if (empty($ids)) {
            return 0;
        }

        /** @var Testimonial[] $testimonials */
        $testimonials = $em->getRepository(Testimonial::class)->findBy(['id' => $ids]);
        foreach ($testimonials as $testimonial) {
            $testimonial->setStatus($status);
        }
        $em->flush();

        return \count($testimonials);
    }

    /**
     * @param AdminContext<Testimonial> $context
     */
    private function getReferer(AdminContext $context): string
    {
        return $context->getRequest()->query->getString('referrer')
            ?: $context->getRequest()->headers->get('referer')
            ?: $this->generateUrl('admin');
    }

    public function configureFilters(Filters $filters): Filters
    {
        $statusChoices = [];
        foreach (TestimonialStatus::cases() as $case) {
            $statusChoices[$case->label()] = $case->value;
        }

        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices($statusChoices));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')
            ->hideOnForm();

        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                'En attente' => TestimonialStatus::Pending,
                'Approuvé' => TestimonialStatus::Approved,
                'Rejeté' => TestimonialStatus::Rejected,
            ])
            ->renderAsBadges([
                TestimonialStatus::Pending->value => 'warning',
                TestimonialStatus::Approved->value => 'success',
                TestimonialStatus::Rejected->value => 'danger',
            ]);

        yield EmailField::new('email', 'E-mail');

        yield TextField::new('displayName', 'Auteur')
            ->hideOnForm();

        yield TextField::new('firstName', 'Prénom')
            ->onlyOnForms();

        yield TextField::new('lastNameInitial', 'Initiale nom')
            ->setHelp('Une seule lettre (ex: B)')
            ->onlyOnForms();

        yield IntegerField::new('rating', 'Note')
            ->setHelp('De 1 à 5')
            ->setFormTypeOption('attr', ['min' => 1, 'max' => 5])
            ->setTemplatePath('admin/field/rating_stars.html.twig');

        yield TextareaField::new('text', 'Témoignage')
            ->hideOnIndex();

        yield TextField::new('text', 'Aperçu')
            ->onlyOnIndex()
            ->formatValue(static fn (?string $value): string => $value !== null && \mb_strlen($value) > 100 ? \mb_substr($value, 0, 100).'…' : ($value ?? ''));

        yield TextField::new('city', 'Ville')
            ->hideOnIndex();

        yield AssociationField::new('relatedOrder', 'Commande')
            ->hideOnForm();

        yield DateTimeField::new('submittedAt', 'Soumis le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Envoyé le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->hideOnForm()
            ->hideOnIndex();
    }
}
