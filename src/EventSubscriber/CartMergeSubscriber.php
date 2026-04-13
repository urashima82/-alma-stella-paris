<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Customer;
use App\Service\CartManager;
use App\Service\ReservationManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Merges the guest cart (cookie/session) into the customer's DB cart on login
 * and transfers product reservations to the new session after session migration.
 */
final class CartMergeSubscriber implements EventSubscriberInterface
{
    private ?string $preLoginSessionId = null;

    public function __construct(
        private readonly CartManager $cartManager,
        private readonly ReservationManager $reservationManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => ['onRequest', 64],
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    /**
     * Capture the session ID at the start of the request, before any
     * authentication triggers session migration (session fixation protection).
     */
    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($event->getRequest()->hasPreviousSession()) {
            $this->preLoginSessionId = $event->getRequest()->getSession()->getId();
        }
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof Customer) {
            return;
        }

        // Transfer product reservations from the pre-login session to the
        // post-login session so they are not treated as "reserved by another".
        if (null !== $this->preLoginSessionId) {
            $this->reservationManager->transferReservations($this->preLoginSessionId);
        }

        $this->cartManager->mergeGuestCartIntoCustomer($user);
    }
}
