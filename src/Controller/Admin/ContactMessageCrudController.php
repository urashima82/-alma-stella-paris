<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Enum\ContactSubject;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\HttpFoundation\Response;

/** @extends AbstractCrudController<ContactMessage> */
class ContactMessageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ContactMessage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Message')
            ->setEntityLabelInPlural('Messages de contact')
            ->setSearchFields(['name', 'email', 'message'])
            ->setDefaultSort(['createdAt' => 'DESC']);
    }

    public function configureActions(Actions $actions): Actions
    {
        $reply = Action::new('reply', 'Répondre', 'fa fa-reply')
            ->linkToUrl(static function (ContactMessage $message): string {
                $subject = \rawurlencode('Re: '.$message->getSubject()->value);
                $email = \rawurlencode($message->getEmail());

                return \sprintf('mailto:%s?subject=%s', $email, $subject);
            });

        $markRead = Action::new('markAsRead', 'Marquer lu', 'fa fa-check')
            ->linkToCrudAction('markAsReadAction')
            ->displayIf(static fn (ContactMessage $msg): bool => !$msg->isRead());

        return $actions
            ->disable(Action::NEW)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $markRead)
            ->add(Crud::PAGE_INDEX, $reply)
            ->add(Crud::PAGE_DETAIL, $reply)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, static fn (Action $action) => $action->setLabel('Voir'))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $action) => $action->setLabel('Supprimer'));
    }

    /**
     * @param AdminContext<ContactMessage> $context
     */
    #[AdminRoute(name: 'markAsRead')]
    public function markAsReadAction(AdminContext $context, EntityManagerInterface $em): Response
    {
        /** @var ContactMessage $message */
        $message = $context->getEntity()->getInstance();
        $message->setIsRead(true);
        $em->flush();

        $this->addFlash('success', 'Message marqué comme lu.');

        $referer = $context->getRequest()->query->getString('referrer')
            ?: $context->getRequest()->headers->get('referer')
            ?: $this->generateUrl('admin');

        return $this->redirect($referer);
    }

    /**
     * @param AdminContext<ContactMessage> $context
     */
    public function detail(AdminContext $context): \EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore|Response
    {
        /** @var ContactMessage $message */
        $message = $context->getEntity()->getInstance();

        if (!$message->isRead()) {
            $message->setIsRead(true);
            /** @var EntityManagerInterface $em */
            $em = $this->container->get('doctrine')->getManager();
            $em->flush();
        }

        return parent::detail($context);
    }

    public function configureFilters(Filters $filters): Filters
    {
        $subjectChoices = [];
        foreach (ContactSubject::cases() as $case) {
            $subjectChoices[$case->value] = $case->value;
        }

        return $filters
            ->add(BooleanFilter::new('isRead', 'Lu'))
            ->add(ChoiceFilter::new('subject', 'Sujet')->setChoices($subjectChoices));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
        yield TextField::new('name', 'Nom');
        yield EmailField::new('email', 'E-mail');
        yield ChoiceField::new('subject', 'Sujet')
            ->setChoices(\array_combine(
                \array_map(static fn (ContactSubject $s): string => $s->value, ContactSubject::cases()),
                ContactSubject::cases(),
            ));
        yield TextareaField::new('message', 'Message')
            ->hideOnIndex();
        yield TextField::new('message', 'Aperçu')
            ->onlyOnIndex()
            ->formatValue(static fn (?string $value): string => $value !== null && \strlen($value) > 100 ? \mb_substr($value, 0, 100).'…' : ($value ?? ''));
        yield BooleanField::new('isRead', 'Lu');
        yield DateTimeField::new('createdAt', 'Reçu le')
            ->setFormat('dd/MM/yyyy HH:mm');
    }
}
