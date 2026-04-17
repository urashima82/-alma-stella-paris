<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\MessengerMessage;
use App\Repository\MessengerMessageRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<MessengerMessage> */
class MessengerMessageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MessengerMessageRepository $messengerMessageRepository,
        private readonly ProductRepository $productRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return MessengerMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Message')
            ->setEntityLabelInPlural('File d\'attente Messenger')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('queueName');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');

        yield TextField::new('messageType', 'Type')
            ->hideOnForm();

        yield TextField::new('queueName', 'File');

        yield BooleanField::new('delivered', 'En cours')
            ->renderAsSwitch(false)
            ->hideOnForm();

        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy HH:mm:ss');

        yield DateTimeField::new('availableAt', 'Disponible à')
            ->setFormat('dd/MM/yyyy HH:mm:ss')
            ->onlyOnDetail();

        yield DateTimeField::new('deliveredAt', 'Pris en charge le')
            ->setFormat('dd/MM/yyyy HH:mm:ss')
            ->onlyOnDetail();

        yield CodeEditorField::new('headers', 'En-têtes')
            ->setLanguage('js')
            ->onlyOnDetail();

        yield CodeEditorField::new('body', 'Contenu')
            ->onlyOnDetail();
    }

    /** @param MessengerMessage $entityInstance */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $queueName = $entityInstance->getQueueName();

        parent::deleteEntity($entityManager, $entityInstance);

        if ($queueName !== 'gemini_generation') {
            return;
        }

        $remaining = $this->messengerMessageRepository->countByQueueName('gemini_generation');
        if ($remaining > 0) {
            return;
        }

        $resetCount = $this->productRepository->resetStuckPendingVisuals();
        if ($resetCount > 0) {
            $this->addFlash('info', \sprintf(
                '%d produit(s) remis en brouillon (plus de génération en attente).',
                $resetCount,
            ));
        }
    }
}
