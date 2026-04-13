<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CleanExpiredReservationsMessage;
use App\Service\ReservationManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CleanExpiredReservationsHandler
{
    public function __construct(
        private readonly ReservationManager $reservationManager,
    ) {
    }

    public function __invoke(CleanExpiredReservationsMessage $message): void
    {
        $this->reservationManager->releaseExpired();
    }
}
