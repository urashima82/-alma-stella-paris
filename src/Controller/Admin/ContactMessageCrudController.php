<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Enum\ContactSubject;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

/** @extends AbstractCrudController<ContactMessage> */
class ContactMessageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ContactMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Message')
            ->setEntityLabelInPlural('Messages de contact')
            ->setSearchFields(['name', 'email', 'message'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, static fn (Action $action) => $action->setLabel('Voir'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action) => $action->setLabel('Supprimer'));
    }

    public function configureFilters(Filters $filters): Filters
    {
        $subjectChoices = [];
        foreach (ContactSubject::cases() as $case) {
            $subjectChoices[$case->value] = $case->value;
        }

        return $filters
            ->add(BooleanFilter::new('isRead', 'Lu'))
            ->add(ChoiceFilter::new('subject', 'Sujet')->setChoices($subjectChoices));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('name', 'Nom');
        yield EmailField::new('email', 'E-mail');
        yield ChoiceField::new('subject', 'Sujet')
            ->setChoices(\array_combine(
                \array_map(static fn (ContactSubject $s): string => $s->value, ContactSubject::cases()),
                ContactSubject::cases(),
            ));
        yield TextareaField::new('message', 'Message')
            ->hideOnIndex();
        yield BooleanField::new('isRead', 'Lu')
            ->renderAsSwitch(false);
        yield DateTimeField::new('createdAt', 'Reçu le')
            ->setFormat('dd/MM/yyyy HH:mm');
    }
}
