<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CleanAbandonedOrdersMessage;
use App\Service\AbandonedOrderCleaner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CleanAbandonedOrdersHandler
{
    public function __construct(
        private readonly AbandonedOrderCleaner $cleaner,
    ) {
    }

    public function __invoke(CleanAbandonedOrdersMessage $message): void
    {
        $this->cleaner->cleanAll();
    }
}
