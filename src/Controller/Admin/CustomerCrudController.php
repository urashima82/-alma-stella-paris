<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Customer;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;

/** @extends AbstractCrudController<Customer> */
class CustomerCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Customer::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Client')
            ->setEntityLabelInPlural('Clients')
            ->setSearchFields(['email', 'firstName', 'lastName'])
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->overrideTemplate('crud/detail', 'admin/customer/detail.html.twig');
    }

    public function __construct(
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public function configureActions(Actions $actions): Actions
    {
        $urlGenerator = $this->adminUrlGenerator;
        $viewOrders = Action::new('viewOrders', 'Ses commandes', 'fa fa-shopping-bag')
            ->linkToUrl(static fn (Customer $customer): string => $urlGenerator
                ->unsetAll()
                ->setController(OrderCrudController::class)
                ->setAction(Action::INDEX)
                ->set('filters[customer][comparison]', '=')
                ->set('filters[customer][value]', $customer->getId())
                ->generateUrl());

        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, static fn (Action $action) => $action->setLabel('Voir'))
            ->add(Crud::PAGE_INDEX, $viewOrders)
            ->add(Crud::PAGE_DETAIL, $viewOrders);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('firstName', 'Prénom');
        yield TextField::new('lastName', 'Nom');
        yield EmailField::new('email', 'E-mail');
        yield TextField::new('loyaltyBadge', 'Fidélité')
            ->renderAsHtml()
            ->hideOnForm()
            ->onlyOnIndex();
        yield IntegerField::new('orderCount', 'Commandes')
            ->hideOnForm()
            ->setTemplatePath('admin/field/order_count.html.twig');
        yield MoneyField::new('totalSpentUsd', 'Total dépensé')
            ->setCurrency('USD')
            ->setStoredAsCents(false)
            ->setNumDecimals(2)
            ->hideOnForm();
        yield DateTimeField::new('lastOrderDate', 'Dernière commande')
            ->setFormat('dd/MM/yyyy')
            ->hideOnForm();
        yield DateTimeField::new('createdAt', 'Inscrit le')
            ->hideOnForm()
            ->setFormat('dd/MM/yyyy HH:mm');
    }
}
