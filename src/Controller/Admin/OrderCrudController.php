<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;

/** @extends AbstractCrudController<Order> */
class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commande')
            ->setEntityLabelInPlural('Commandes')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['reference', 'customerName', 'customerEmail', 'trackingNumber'])
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/edit', 'admin/order/edit.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action) => $action->setLabel('Gérer'));
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status', 'Statut')->setChoices([
                'En attente' => OrderStatus::Pending->value,
                'En préparation' => OrderStatus::Processing->value,
                'Expédiée' => OrderStatus::Shipped->value,
                'Livrée' => OrderStatus::Delivered->value,
                'Annulée' => OrderStatus::Cancelled->value,
            ]))
            ->add(DateTimeFilter::new('createdAt', 'Date de commande'));
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return $this->indexFields();
        }

        return $this->editFields();
    }

    /**
     * @return iterable<mixed>
     */
    private function indexFields(): iterable
    {
        yield TextField::new('reference', 'Réf.');
        yield TextField::new('statusLabel', 'Statut')
            ->renderAsHtml();
        yield TextField::new('customerName', 'Client');
        yield EmailField::new('customerEmail', 'Email');
        yield IntegerField::new('itemCount', 'Articles')
            ->formatValue(static fn ($value, Order $entity) => $entity->getItems()->count());
        yield MoneyField::new('totalUsd', 'Total')
            ->setCurrency('USD')
            ->setStoredAsCents(false)
            ->setNumDecimals(2);
        yield CountryField::new('shippingCountry', 'Destination');
        yield TextField::new('trackingNumber', 'N° suivi');
        yield AssociationField::new('customer', 'Compte')
            ->setCrudController(CustomerCrudController::class)
            ->formatValue(static fn ($value, Order $entity) => $entity->getCustomer()?->getFullName() ?? '-');
        yield DateTimeField::new('createdAt', 'Date')
            ->setFormat('dd/MM/yyyy HH:mm');
    }

    /**
     * @return iterable<mixed>
     */
    private function editFields(): iterable
    {
        yield FormField::addFieldset('Gestion', 'fa fa-cog');
        yield ChoiceField::new('status', 'Statut')
            ->setChoices(self::statusChoices());
        yield TextField::new('trackingNumber', 'N° de suivi')
            ->setRequired(false)
            ->setHelp('Obligatoire pour passer au statut "Expédiée". L\'email sera envoyé automatiquement au client.');
        yield TextareaField::new('internalNotes', 'Notes internes')
            ->setHelp('Non visibles par le client')
            ->setRequired(false);
    }

    /**
     * @return array<string, OrderStatus>
     */
    private static function statusChoices(): array
    {
        return [
            OrderStatus::Pending->label() => OrderStatus::Pending,
            OrderStatus::Processing->label() => OrderStatus::Processing,
            OrderStatus::Shipped->label() => OrderStatus::Shipped,
            OrderStatus::Delivered->label() => OrderStatus::Delivered,
            OrderStatus::Cancelled->label() => OrderStatus::Cancelled,
        ];
    }
}
