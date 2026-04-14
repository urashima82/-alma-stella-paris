<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\TestimonialMailer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-testimonial-requests',
    description: 'Send testimonial request emails for delivered orders (J+14).',
)]
final class SendTestimonialRequestsCommand extends Command
{
    public function __construct(
        private readonly TestimonialMailer $testimonialMailer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->testimonialMailer->sendPendingRequests();

        $io->success('Testimonial request emails processed.');

        return Command::SUCCESS;
    }
}
