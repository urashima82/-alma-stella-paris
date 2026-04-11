<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Admin;
use App\Enum\AdminRole;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/** @extends AbstractCrudController<Admin> */
class AdminCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Admin::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Administrateur')
            ->setEntityLabelInPlural('Administrateurs')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['email'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        $isSuperAdmin = $this->isGranted('ROLE_SUPER_ADMIN');

        if (!$isSuperAdmin) {
            return $actions
                ->disable(Action::NEW, Action::EDIT, Action::DELETE);
        }

        return $actions
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $action) => $action->setLabel('Modifier'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static function (Action $action): Action {
                return $action->displayIf(static function (Admin $admin): bool {
                    return !$admin->isSuperAdmin();
                });
            });
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return $this->indexFields();
        }

        return $this->formFields();
    }

    /**
     * @return iterable<mixed>
     */
    private function indexFields(): iterable
    {
        yield EmailField::new('email', 'Email');
        yield TextField::new('roleLabel', 'Rôle')
            ->renderAsHtml();
        yield BooleanField::new('receivesAdminEmails', 'Reçoit les emails')
            ->setDisabled(!$this->isGranted('ROLE_SUPER_ADMIN'));
        yield DateTimeField::new('lastLoggedInAt', 'Dernière connexion')
            ->setFormat('dd/MM/yyyy HH:mm');
        yield DateTimeField::new('createdAt', 'Créé le')
            ->setFormat('dd/MM/yyyy');
    }

    /**
     * @return iterable<mixed>
     */
    private function formFields(): iterable
    {
        yield EmailField::new('email', 'Email');
        yield ChoiceField::new('role', 'Rôle')
            ->setChoices([
                AdminRole::SuperAdmin->label() => AdminRole::SuperAdmin,
                AdminRole::Admin->label() => AdminRole::Admin,
            ]);
        yield BooleanField::new('receivesAdminEmails', 'Reçoit les emails admin')
            ->setHelp('Si activé, cet administrateur recevra les notifications par email (nouvelles commandes, etc.)');
    }
}
