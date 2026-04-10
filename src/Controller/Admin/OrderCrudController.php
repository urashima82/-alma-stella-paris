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
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

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
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action) => $action->setLabel('Gérer'))
            ->update(Crud::PAGE_DETAIL, Action::EDIT, static fn (Action $action) => $action->setLabel('Modifier'))
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, static fn (Action $action) => $action->setLabel('Voir'));
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
            ->add('originCountry');
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id');
            yield TextField::new('reference', 'Référence');
            yield ChoiceField::new('status', 'Statut')
                ->setChoices([
                    OrderStatus::Pending->label() => OrderStatus::Pending,
                    OrderStatus::Processing->label() => OrderStatus::Processing,
                    OrderStatus::Shipped->label() => OrderStatus::Shipped,
                    OrderStatus::Delivered->label() => OrderStatus::Delivered,
                    OrderStatus::Cancelled->label() => OrderStatus::Cancelled,
                ])
                ->renderAsBadges([
                    OrderStatus::Pending->value => 'warning',
                    OrderStatus::Processing->value => 'info',
                    OrderStatus::Shipped->value => 'primary',
                    OrderStatus::Delivered->value => 'success',
                    OrderStatus::Cancelled->value => 'danger',
                ]);
            yield TextField::new('customerName', 'Client');
            yield MoneyField::new('totalUsd', 'Total USD')
                ->setCurrency('USD')
                ->setStoredAsCents(false)
                ->setNumDecimals(2);
            yield ChoiceField::new('originCountry', 'Pays d\'origine')
                ->setChoices(['France' => 'FR', 'Mexique' => 'MX'])
                ->renderAsBadges(['FR' => 'info', 'MX' => 'warning']);
            yield TextField::new('trackingNumber', 'N° de suivi');
            yield DateTimeField::new('createdAt', 'Date')
                ->setFormat('dd/MM/yyyy HH:mm');

            return;
        }

        if (Crud::PAGE_DETAIL === $pageName) {
            yield TextField::new('reference', 'Référence');
            yield ChoiceField::new('status', 'Statut')
                ->setChoices([
                    OrderStatus::Pending->label() => OrderStatus::Pending,
                    OrderStatus::Processing->label() => OrderStatus::Processing,
                    OrderStatus::Shipped->label() => OrderStatus::Shipped,
                    OrderStatus::Delivered->label() => OrderStatus::Delivered,
                    OrderStatus::Cancelled->label() => OrderStatus::Cancelled,
                ])
                ->renderAsBadges([
                    OrderStatus::Pending->value => 'warning',
                    OrderStatus::Processing->value => 'info',
                    OrderStatus::Shipped->value => 'primary',
                    OrderStatus::Delivered->value => 'success',
                    OrderStatus::Cancelled->value => 'danger',
                ]);
            yield TextField::new('customerName', 'Client');
            yield EmailField::new('customerEmail', 'Email');
            yield MoneyField::new('totalUsd', 'Total USD')
                ->setCurrency('USD')
                ->setStoredAsCents(false)
                ->setNumDecimals(2);
            yield ChoiceField::new('originCountry', 'Pays d\'origine')
                ->setChoices(['France' => 'FR', 'Mexique' => 'MX'])
                ->renderAsBadges(['FR' => 'info', 'MX' => 'warning']);
            yield TextField::new('trackingNumber', 'N° de suivi');
            yield DateTimeField::new('createdAt', 'Date')
                ->setFormat('dd/MM/yyyy HH:mm');
            yield TextField::new('shippingAddressLine1', 'Adresse ligne 1');
            yield TextField::new('shippingAddressLine2', 'Adresse ligne 2');
            yield TextField::new('shippingCity', 'Ville');
            yield TextField::new('shippingState', 'État / Province');
            yield TextField::new('shippingPostalCode', 'Code postal');
            yield TextField::new('shippingCountry', 'Pays de livraison');
            yield CollectionField::new('items', 'Articles')
                ->useEntryCrudForm(OrderItemCrudController::class);
            yield TextField::new('stripePaymentStatus', 'Statut paiement Stripe');
            yield TextField::new('stripePaymentIntentId', 'Stripe PaymentIntent');
            yield DateTimeField::new('updatedAt', 'Dernière modification')
                ->setFormat('dd/MM/yyyy HH:mm');

            return;
        }

        // --- Edit form (with tabs) ---
        yield FormField::addTab('Commande');

        yield TextField::new('reference', 'Référence')
            ->setDisabled();
        yield ChoiceField::new('status', 'Statut')
            ->setChoices([
                OrderStatus::Pending->label() => OrderStatus::Pending,
                OrderStatus::Processing->label() => OrderStatus::Processing,
                OrderStatus::Shipped->label() => OrderStatus::Shipped,
                OrderStatus::Delivered->label() => OrderStatus::Delivered,
                OrderStatus::Cancelled->label() => OrderStatus::Cancelled,
            ]);
        yield ChoiceField::new('originCountry', 'Pays d\'origine')
            ->setChoices(['France' => 'FR', 'Mexique' => 'MX'])
            ->setRequired(false);
        yield TextField::new('trackingNumber', 'N° de suivi')
            ->setRequired(false);

        yield FormField::addTab('Livraison');

        yield TextField::new('customerName', 'Client')
            ->setDisabled();
        yield EmailField::new('customerEmail', 'Email')
            ->setDisabled();
        yield TextField::new('shippingAddressLine1', 'Adresse ligne 1')
            ->setDisabled();
        yield TextField::new('shippingAddressLine2', 'Adresse ligne 2')
            ->setDisabled();
        yield TextField::new('shippingCity', 'Ville')
            ->setDisabled();
        yield TextField::new('shippingState', 'État / Province')
            ->setDisabled();
        yield TextField::new('shippingPostalCode', 'Code postal')
            ->setDisabled();
        yield TextField::new('shippingCountry', 'Pays de livraison')
            ->setDisabled();

        yield FormField::addTab('Articles');

        yield CollectionField::new('items', 'Articles')
            ->setDisabled()
            ->useEntryCrudForm(OrderItemCrudController::class);

        yield FormField::addTab('Paiement');

        yield MoneyField::new('totalUsd', 'Total USD')
            ->setCurrency('USD')
            ->setStoredAsCents(false)
            ->setNumDecimals(2)
            ->setDisabled();
        yield TextField::new('stripePaymentStatus', 'Statut paiement Stripe')
            ->setDisabled();
        yield TextField::new('stripePaymentIntentId', 'Stripe PaymentIntent')
            ->setDisabled();
        yield DateTimeField::new('createdAt', 'Créée le')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setDisabled();
        yield DateTimeField::new('updatedAt', 'Dernière modification')
            ->setFormat('dd/MM/yyyy HH:mm')
            ->setDisabled();
    }
}
