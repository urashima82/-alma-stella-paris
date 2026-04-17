<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\SourcePhoto;
use App\Enum\PhotoAngle;
use App\Service\Visual\ImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints as Assert;

/** @extends AbstractCrudController<SourcePhoto> */
class SourcePhotoCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ImageStorage $imageStorage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SourcePhoto::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Photo source')
            ->setEntityLabelInPlural('Photos sources')
            ->setDefaultSort(['product' => 'ASC', 'position' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::EDIT);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('product')
            ->add('angle');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        yield ImageField::new('path', 'Aperçu')
            ->setBasePath('/storage/products')
            ->onlyOnIndex();

        yield AssociationField::new('product', 'Produit');

        if ($pageName === Crud::PAGE_NEW) {
            yield TextField::new('sourceFile', 'Photo')
                ->setFormType(FileType::class)
                ->setFormTypeOptions([
                    'constraints' => [
                        new Assert\NotNull(message: 'Une photo est requise.'),
                        new Assert\File(
                            maxSize: '10M',
                            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                            mimeTypesMessage: 'Formats acceptés : JPG, PNG, WebP.',
                        ),
                    ],
                ])
                ->setHelp('Formats acceptés : JPG, PNG, WebP. Taille max : 10 Mo.');
        }

        yield ChoiceField::new('angle', 'Angle')
            ->setChoices([
                PhotoAngle::Front->label() => PhotoAngle::Front,
                PhotoAngle::ThreeQuarter->label() => PhotoAngle::ThreeQuarter,
                PhotoAngle::Detail->label() => PhotoAngle::Detail,
                PhotoAngle::Back->label() => PhotoAngle::Back,
                PhotoAngle::Other->label() => PhotoAngle::Other,
            ]);

        yield IntegerField::new('position', 'Position')
            ->onlyOnIndex();
    }

    public function createEntity(string $entityFqcn): SourcePhoto
    {
        $sourcePhoto = new SourcePhoto();

        $productId = $this->getContext()?->getRequest()?->query->get('productId');
        if ($productId !== null) {
            $product = $this->entityManager->getRepository(Product::class)->find((int) $productId);
            if ($product !== null) {
                $sourcePhoto->setProduct($product);
            }
        }

        return $sourcePhoto;
    }

    /** @param SourcePhoto $entityInstance */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $product = $entityInstance->getProduct();
        if ($product === null) {
            $this->addFlash('danger', 'Un produit est requis.');

            return;
        }

        $file = $entityInstance->getSourceFile();
        if ($file === null) {
            $this->addFlash('danger', 'Une photo est requise.');

            return;
        }

        $position = $product->getSourcePhotos()->count() + 1;
        $entityInstance->setPosition($position);

        $path = $this->imageStorage->storeSourcePhoto($file, $product);
        $entityInstance->setPath($path);

        parent::persistEntity($entityManager, $entityInstance);
    }

    /** @param SourcePhoto $entityInstance */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->imageStorage->delete($entityInstance->getPath());

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
