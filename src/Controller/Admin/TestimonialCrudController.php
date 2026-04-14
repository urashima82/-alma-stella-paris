<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Testimonial;
use App\Enum\TestimonialStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
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
        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, static fn (Action $action) => $action->setLabel('Voir'))
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action) => $action->setLabel('Modérer'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action) => $action->setLabel('Supprimer'));
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

        yield EmailField::new('email', 'E-mail')
            ->setFormTypeOption('disabled', true);

        yield TextField::new('displayName', 'Auteur')
            ->hideOnForm();

        yield IntegerField::new('rating', 'Note')
            ->setFormTypeOption('disabled', true)
            ->setTemplatePath('admin/field/rating_stars.html.twig');

        yield TextareaField::new('text', 'Témoignage')
            ->setFormTypeOption('disabled', true)
            ->hideOnIndex();

        yield TextField::new('city', 'Ville')
            ->setFormTypeOption('disabled', true)
            ->hideOnIndex();

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
