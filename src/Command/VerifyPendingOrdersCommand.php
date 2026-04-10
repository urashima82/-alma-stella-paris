<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\PendingOrderVerifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:verify-pending-orders',
    description: 'Verify Stripe payment status for pending orders and confirm or cancel them.',
)]
final class VerifyPendingOrdersCommand extends Command
{
    public function __construct(
        private readonly PendingOrderVerifier $verifier,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->verifier->verifyAll();

        $total = $result['confirmed'] + $result['cancelled'] + $result['skipped'];

        if (0 === $total) {
            $io->info('No pending orders to verify.');
        } else {
            $io->info(\sprintf(
                'Done: %d confirmed, %d cancelled, %d skipped.',
                $result['confirmed'],
                $result['cancelled'],
                $result['skipped'],
            ));
        }

        return Command::SUCCESS;
    }
}
