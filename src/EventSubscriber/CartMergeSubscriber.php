<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Customer;
use App\Service\CartManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Merges the guest cart (cookie/session) into the customer's DB cart on login.
 */
final class CartMergeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CartManager $cartManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof Customer) {
            return;
        }

        $this->cartManager->mergeGuestCartIntoCustomer($user);
    }
}
