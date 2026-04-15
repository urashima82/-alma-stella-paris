<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ProductCategory;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<ProductCategory> */
class ProductCategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductCategory::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Catégorie')
            ->setEntityLabelInPlural('Catégories')
            ->setDefaultSort(['position' => 'ASC'])
            ->overrideTemplate('crud/index', 'admin/category/index.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action->setLabel('Ajouter une catégorie'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm()->setSortable(false);

        yield TextField::new('treeSortKey', '')
            ->hideOnForm()
            ->setLabel(false)
            ->addCssClass('ea-sortable-cell')
            ->setTemplatePath('admin/field/sortable_handle.html.twig');

        yield TextField::new('treeLabel', 'Catégorie')
            ->hideOnForm()
            ->setTemplatePath('admin/field/category_tree_label.html.twig');

        yield TextField::new('name', 'Nom (EN)')
            ->onlyOnForms();
        yield TextField::new('nameFr', 'Nom (FR)')
            ->onlyOnForms();
        yield SlugField::new('slug')->setTargetFieldName('name')
            ->onlyOnForms();
        yield SlugField::new('slugFr', 'Slug (FR)')->setTargetFieldName('nameFr')
            ->onlyOnForms();

        yield AssociationField::new('parent', 'Catégorie parente')
            ->onlyOnForms()
            ->setRequired(false)
            ->setQueryBuilder(static fn (QueryBuilder $qb): QueryBuilder => $qb
                ->andWhere('entity.parent IS NULL')
                ->orderBy('entity.position', 'ASC')
            )
            ->setHelp('Laisser vide pour une catégorie racine.');

        yield IntegerField::new('position', 'Ordre')
            ->onlyOnForms();

        yield IntegerField::new('totalPublishedProductCount', 'Produits publiés')
            ->hideOnForm()
            ->setTemplatePath('admin/field/order_count.html.twig');
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $alias = $qb->getRootAliases()[0];
        $qb->leftJoin($alias.'.parent', 'p')
            ->addSelect('p')
            ->addSelect('IFNULL(p.position, '.$alias.'.position) AS HIDDEN sortGroup')
            ->addSelect('IFNULL(p.id, 0) AS HIDDEN isChild')
            ->orderBy('sortGroup', 'ASC')
            ->addOrderBy('isChild', 'ASC')
            ->addOrderBy($alias.'.position', 'ASC');

        return $qb;
    }
}
