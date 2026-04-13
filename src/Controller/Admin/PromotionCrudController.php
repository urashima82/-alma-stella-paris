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
use Symfony\Component\HttpFoundation\RequestStack;

/** @extends AbstractCrudController<Promotion> */
class PromotionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

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
        $baseUrl = $this->getSiteBaseUrl();

        // ── Index ──
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id');
            yield BooleanField::new('isActive', 'Active');
            yield TextField::new('name', 'Nom');
            yield TextField::new('code', 'Code');
            yield TextField::new('typeLabel', 'Type');
            yield TextField::new('discountLabel', 'Réduction');
            yield TextField::new('shareableLink', 'Lien')
                ->formatValue(static function ($value, Promotion $entity) use ($baseUrl): string {
                    $code = $entity->getCode();
                    if (null === $code || '' === $code) {
                        return '<span class="text-body-secondary">—</span>';
                    }

                    $url = $baseUrl.'?promo='.$code;

                    return \sprintf(
                        '<a href="%s" target="_blank" class="text-truncate d-inline-block" style="max-width:180px" title="%s">%s</a>',
                        \htmlspecialchars($url),
                        \htmlspecialchars($url),
                        \htmlspecialchars($url),
                    );
                });
            yield IntegerField::new('usageCount', 'Utilisations');
            yield DateTimeField::new('endsAt', 'Fin');

            return;
        }

        // ── Detail ──
        if (Crud::PAGE_DETAIL === $pageName) {
            yield FormField::addTab('Informations');
            yield IdField::new('id');
            yield TextField::new('name', 'Nom');
            yield TextField::new('code', 'Code');
            yield TextField::new('shareableLink', 'Lien à partager')
                ->formatValue(static function ($value, Promotion $entity) use ($baseUrl): string {
                    $code = $entity->getCode();
                    if (null === $code || '' === $code) {
                        return '<span class="text-body-secondary">Aucun (promotion automatique)</span>';
                    }

                    $url = $baseUrl.'?promo='.$code;
                    $id = 'promo-url-'.$entity->getId();

                    return \sprintf(
                        '<div class="d-flex align-items-center gap-2">'
                        .'<code id="%s" class="px-2 py-1 bg-light border rounded text-break" style="font-size:13px;">%s</code>'
                        .'<button type="button" class="btn btn-sm btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById(\'%s\').textContent).then(()=>{this.textContent=\'✓ Copié\';setTimeout(()=>{this.textContent=\'Copier\'},2000)})">'
                        .'Copier</button>'
                        .'<a href="%s" target="_blank" class="btn btn-sm btn-outline-primary">Ouvrir</a>'
                        .'</div>',
                        $id,
                        \htmlspecialchars($url),
                        $id,
                        \htmlspecialchars($url),
                    );
                });
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

        yield TextField::new('name', 'Nom')
            ->setHelp('Nom interne de la promotion (ex: "Soldes été 2026")');

        yield ChoiceField::new('type', 'Type de promotion')
            ->setChoices(\array_combine(
                \array_map(static fn (PromotionType $t) => $t->label(), PromotionType::cases()),
                PromotionType::cases(),
            ))
            ->setHelp('Code promo et Lien privé nécessitent un code');

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

    private function getSiteBaseUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            return 'https://almastellaparis.com';
        }

        $host = $request->getSchemeAndHttpHost();

        // In dev/DDEV, use the actual host; in production, use the real domain
        return $host;
    }
}
