<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\VerifyPendingOrdersMessage;
use App\Service\PendingOrderVerifier;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class VerifyPendingOrdersHandler
{
    public function __construct(
        private readonly PendingOrderVerifier $verifier,
    ) {
    }

    public function __invoke(VerifyPendingOrdersMessage $message): void
    {
        $this->verifier->verifyAll();
    }
}
