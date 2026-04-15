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
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

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
            ->add(DateTimeFilter::new('createdAt', 'Date de commande'))
            ->add(EntityFilter::new('customer', 'Client'));
    }

    public function configureFields(string $pageName): iterable
    {
        if ($pageName === Crud::PAGE_INDEX) {
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
            ->renderAsHtml()
            ->formatValue(static function (string $value, Order $entity): string {
                $html = $value;
                if ($entity->getStatus() === OrderStatus::Pending) {
                    $hours = (new \DateTimeImmutable())->getTimestamp() - $entity->getCreatedAt()->getTimestamp();
                    $hours = (int) ($hours / 3600);
                    if ($hours >= 24) {
                        $days = (int) ($hours / 24);
                        $html .= \sprintf(
                            ' <span style="color:#ef4444;font-size:0.7rem;font-weight:600;" title="En attente depuis %d jour%s">⚠ %dj</span>',
                            $days,
                            $days > 1 ? 's' : '',
                            $days,
                        );
                    }
                }

                return $html;
            });
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
