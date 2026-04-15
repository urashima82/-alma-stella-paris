<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\OrderItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<OrderItem> */
class OrderItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OrderItem::class;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('productName', 'Produit')
            ->setDisabled();

        yield MoneyField::new('productPrice', 'Prix')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setNumDecimals(2)
            ->setDisabled();

        yield MoneyField::new('shippingCost', 'Livraison')
            ->setCurrency('EUR')
            ->setStoredAsCents(false)
            ->setNumDecimals(2)
            ->setDisabled();

        yield NumberField::new('lineTotal', 'Total ligne')
            ->setNumDecimals(2)
            ->formatValue(static fn (mixed $value): string => \number_format((float) ($value ?? 0), 2).' €')
            ->setDisabled();
    }
}
