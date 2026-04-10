<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class EasyAdminFlashSubscriber implements EventSubscriberInterface
{
    private int $deleteCount = 0;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => 'onPersist',
            AfterEntityUpdatedEvent::class => 'onUpdate',
            AfterEntityDeletedEvent::class => 'onDelete',
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    /** @param AfterEntityPersistedEvent<object> $event */
    public function onPersist(AfterEntityPersistedEvent $event): void
    {
        $this->addFlash('success', 'L\'élément a été créé avec succès.');
    }

    /** @param AfterEntityUpdatedEvent<object> $event */
    public function onUpdate(AfterEntityUpdatedEvent $event): void
    {
        $this->addFlash('success', 'Les modifications ont été enregistrées.');
    }

    /** @param AfterEntityDeletedEvent<object> $event */
    public function onDelete(AfterEntityDeletedEvent $event): void
    {
        ++$this->deleteCount;
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || 0 === $this->deleteCount) {
            return;
        }

        $message = 1 === $this->deleteCount
            ? 'L\'élément a été supprimé.'
            : \sprintf('%d éléments supprimés.', $this->deleteCount);

        $this->addFlash('success', $message);
        $this->deleteCount = 0;
    }

    private function addFlash(string $type, string $message): void
    {
        /** @var \Symfony\Component\HttpFoundation\Session\Session $session */
        $session = $this->requestStack->getSession();
        $session->getFlashBag()->add($type, $message);
    }
}
