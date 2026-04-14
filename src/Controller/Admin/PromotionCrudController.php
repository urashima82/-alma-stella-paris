<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Promotion;
use App\Enum\DiscountType;
use App\Enum\PromotionType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<Promotion> */
class PromotionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Promotion::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Promotion')
            ->setEntityLabelInPlural('Promotions')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['name', 'code']);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('isActive')
            ->add('type')
            ->add('discountType');
    }

    public function configureFields(string $pageName): iterable
    {
        // ── Index ──
        if ($pageName === Crud::PAGE_INDEX) {
            yield IdField::new('id');
            yield BooleanField::new('isActive', 'Active');
            yield TextField::new('name', 'Nom (EN)');
            yield TextField::new('nameFr', 'Nom (FR)');
            yield TextField::new('code', 'Code');
            yield TextField::new('typeLabel', 'Type');
            yield TextField::new('discountLabel', 'Réduction');
            yield IntegerField::new('usageCount', 'Utilisations');
            yield DateTimeField::new('endsAt', 'Fin');

            return;
        }

        // ── Detail ──
        if ($pageName === Crud::PAGE_DETAIL) {
            yield FormField::addTab('Informations');
            yield IdField::new('id');
            yield TextField::new('name', 'Nom (EN)');
            yield TextField::new('nameFr', 'Nom (FR)');
            yield TextField::new('code', 'Code');
            yield TextField::new('typeLabel', 'Type');
            yield TextField::new('discountTypeLabel', 'Type de réduction');
            yield NumberField::new('discountValue', 'Valeur');
            yield BooleanField::new('isActive', 'Active')->renderAsSwitch(false);
            yield BooleanField::new('isCumulable', 'Cumulable')->renderAsSwitch(false);
            yield BooleanField::new('overridesCompareAtPrice', 'Remplace le prix barré')->renderAsSwitch(false);

            yield FormField::addTab('Conditions');
            yield DateTimeField::new('startsAt', 'Début');
            yield DateTimeField::new('endsAt', 'Fin');
            yield IntegerField::new('maxUsages', 'Utilisations max');
            yield IntegerField::new('maxUsagesPerEmail', 'Max par email');
            yield NumberField::new('minimumAmountUsd', 'Montant min (USD)');
            yield AssociationField::new('products', 'Produits ciblés');
            yield AssociationField::new('categories', 'Catégories ciblées');

            yield FormField::addTab('Statistiques');
            yield IntegerField::new('usageCount', 'Utilisations');
            yield NumberField::new('revenueGeneratedUsd', 'Revenu généré (USD)')
                ->setNumDecimals(2);
            yield DateTimeField::new('lastUsedAt', 'Dernière utilisation');
            yield DateTimeField::new('createdAt', 'Créée le');
            yield DateTimeField::new('updatedAt', 'Modifiée le');

            return;
        }

        // ── New / Edit ──
        yield FormField::addTab('Général');

        yield TextField::new('name', 'Nom (EN)')
            ->setHelp('Nom affiché aux clients anglophones (ex: "Summer sale 2026")');

        yield TextField::new('nameFr', 'Nom (FR)')
            ->setHelp('Nom affiché aux clients francophones (ex: "Soldes été 2026")');

        yield ChoiceField::new('type', 'Type de promotion')
            ->setChoices(\array_combine(
                \array_map(static fn (PromotionType $t) => $t->label(), PromotionType::cases()),
                PromotionType::cases(),
            ))
            ->setHelp('Le type Code promo nécessite un code');

        yield TextField::new('code', 'Code')
            ->setHelp('Laissez vide pour les promos automatiques. Sera converti en majuscules.')
            ->setRequired(false);

        yield ChoiceField::new('discountType', 'Type de réduction')
            ->setChoices(\array_combine(
                \array_map(static fn (DiscountType $d) => $d->label(), DiscountType::cases()),
                DiscountType::cases(),
            ));

        yield NumberField::new('discountValue', 'Valeur de la réduction')
            ->setHelp('Ex: 10 pour 10% ou 5.00 pour $5')
            ->setNumDecimals(2);

        yield BooleanField::new('isActive', 'Active');

        yield FormField::addTab('Règles');

        yield BooleanField::new('isCumulable', 'Cumulable')
            ->setHelp('Si activé, cette promo peut se cumuler avec d\'autres');

        yield BooleanField::new('overridesCompareAtPrice', 'Remplace le prix barré existant')
            ->setHelp('Si désactivé, la promo ne s\'applique pas aux produits qui ont déjà un prix barré manuel');

        yield FormField::addTab('Conditions');

        yield DateTimeField::new('startsAt', 'Date de début')
            ->setRequired(false)
            ->setHelp('Laissez vide pour une activation immédiate');

        yield DateTimeField::new('endsAt', 'Date de fin')
            ->setRequired(false)
            ->setHelp('Laissez vide pour une durée illimitée');

        yield IntegerField::new('maxUsages', 'Nombre max d\'utilisations')
            ->setRequired(false)
            ->setHelp('Laissez vide pour illimité');

        yield IntegerField::new('maxUsagesPerEmail', 'Max par email')
            ->setRequired(false)
            ->setHelp('Laissez vide pour illimité par client');

        yield NumberField::new('minimumAmountUsd', 'Montant minimum du panier (USD)')
            ->setRequired(false)
            ->setNumDecimals(2)
            ->setHelp('Laissez vide pour aucun minimum');

        yield FormField::addTab('Ciblage');

        yield AssociationField::new('products', 'Produits ciblés')
            ->setRequired(false)
            ->setHelp('Laissez vide pour appliquer à tous les produits')
            ->autocomplete();

        yield AssociationField::new('categories', 'Catégories ciblées')
            ->setRequired(false)
            ->setHelp('Laissez vide pour appliquer à toutes les catégories')
            ->autocomplete();
    }
}
