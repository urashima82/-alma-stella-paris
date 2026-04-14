<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ReservationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-expired-reservations',
    description: 'Release all expired product reservations.',
)]
final class CleanExpiredReservationsCommand extends Command
{
    public function __construct(
        private readonly ReservationManager $reservationManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->reservationManager->releaseExpired();

        if ($count === 0) {
            $io->info('No expired reservations.');
        } else {
            $io->success(\sprintf('Released %d expired reservation(s).', $count));
        }

        return Command::SUCCESS;
    }
}
