<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ShippingSettings;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<ShippingSettings> */
class ShippingSettingsCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ShippingSettings::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Tranche d\'expédition')
            ->setEntityLabelInPlural('Frais de port')
            ->setPageTitle(Crud::PAGE_INDEX, 'Frais de port')
            ->setPageTitle(Crud::PAGE_EDIT, 'Modifier la tranche d\'expédition')
            ->setDefaultSort(['tier' => 'ASC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();
        yield TextField::new('tier.value', 'Type')
            ->setTemplatePath('admin/field/shipping_tier_badge.html.twig')
            ->hideOnForm();
        yield TextField::new('label', 'Libellé');
        yield MoneyField::new('shippingCostUsd', 'Frais de port (USD)')
            ->setCurrency('USD')
            ->setStoredAsCents(false)
            ->setNumDecimals(2);
        yield NumberField::new('maxWeightGrams', 'Poids max (g)')
            ->setNumDecimals(0);
    }
}
