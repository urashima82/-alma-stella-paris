<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * Workaround for Symfony TraceableEventDispatcher bug:
 * "Undefined array key WorkerRunningEvent" when no listeners are registered.
 */
#[AsEventListener(event: WorkerRunningEvent::class)]
final class MessengerWorkerListener
{
    public function __invoke(WorkerRunningEvent $event): void
    {
    }
}
