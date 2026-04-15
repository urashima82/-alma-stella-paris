<?php

declare(strict_types=1);

namespace App;

use App\Message\CleanAbandonedOrdersMessage;
use App\Message\CleanExpiredReservationsMessage;
use App\Message\SendTestimonialRequestsMessage;
use App\Message\VerifyPendingOrdersMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(RecurringMessage::every('5 minutes', new VerifyPendingOrdersMessage()))
            ->add(RecurringMessage::every('5 minutes', new CleanExpiredReservationsMessage()))
            ->add(RecurringMessage::every('6 hours', new SendTestimonialRequestsMessage()))
            ->add(RecurringMessage::every('1 hour', new CleanAbandonedOrdersMessage()));
    }
}
