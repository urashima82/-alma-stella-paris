<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendTestimonialRequestsMessage;
use App\Service\TestimonialMailer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SendTestimonialRequestsHandler
{
    public function __construct(
        private readonly TestimonialMailer $testimonialMailer,
    ) {
    }

    public function __invoke(SendTestimonialRequestsMessage $message): void
    {
        $this->testimonialMailer->sendPendingRequests();
    }
}
