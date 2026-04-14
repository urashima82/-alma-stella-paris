<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\SiteSettings;
use App\Repository\SiteSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;

/** @extends AbstractCrudController<SiteSettings> */
class SiteSettingsCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly SiteSettingsRepository $siteSettingsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return SiteSettings::class;
    }

    public function index(AdminContext $context): KeyValueStore|Response
    {
        $settings = $this->siteSettingsRepository->findOneBy([]);

        if ($settings === null) {
            $settings = new SiteSettings();
            $this->entityManager->persist($settings);
            $this->entityManager->flush();
        }

        $url = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($settings->getId())
            ->generateUrl();

        return $this->redirect($url);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Paramètres du site')
            ->setEntityLabelInPlural('Paramètres du site')
            ->setPageTitle(Crud::PAGE_EDIT, 'Paramètres du site');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::BATCH_DELETE, Action::INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::new('backToDashboard', 'Retour au tableau de bord')
                ->linkToUrl('/admin')
                ->setCssClass('btn btn-secondary')
                ->createAsGlobalAction());
    }

    public function configureFields(string $pageName): iterable
    {
        yield FormField::addTab('Général', 'fa fa-cog');

        yield ChoiceField::new('activeCollection', 'Collection affichée sur le site')
            ->setChoices([
                'Toutes les collections' => SiteSettings::COLLECTION_ALL,
                'Collection France' => SiteSettings::COLLECTION_FRANCE,
                'Collection Mexique' => SiteSettings::COLLECTION_MEXICO,
            ])
            ->renderExpanded()
            ->setHelp('Choisissez quelle collection est visible pour les visiteurs du site.');

        yield FormField::addTab('Mode maintenance', 'fa fa-wrench');

        yield BooleanField::new('isMaintenanceMode', 'Activer le mode maintenance')
            ->setHelp('Quand activé, les visiteurs voient une page de maintenance au lieu du site.');

        yield TextareaField::new('maintenanceMessage', 'Message de maintenance (FR)')
            ->setHelp('Message affiché aux visiteurs francophones. Laissez vide pour le message par défaut.')
            ->setRequired(false)
            ->setNumOfRows(4);

        yield TextareaField::new('maintenanceMessageEn', 'Message de maintenance (EN)')
            ->setHelp('Message displayed to English-speaking visitors. Leave empty for the default message.')
            ->setRequired(false)
            ->setNumOfRows(4);

        yield TextField::new('maintenanceAllowedIpsAsString', 'IPs autorisées')
            ->setHelp('Adresses IP autorisées à voir le site en maintenance, séparées par des virgules (ex: 82.120.45.12, 192.168.1.1).')
            ->setRequired(false);
    }
}
