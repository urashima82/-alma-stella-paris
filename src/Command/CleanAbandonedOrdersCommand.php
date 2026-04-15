<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AbandonedOrderCleaner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-abandoned-orders',
    description: 'Cancel pending orders without payment that have been abandoned for more than 24 hours.',
)]
final class CleanAbandonedOrdersCommand extends Command
{
    public function __construct(
        private readonly AbandonedOrderCleaner $cleaner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->cleaner->cleanAll();

        if ($result['deleted'] === 0) {
            $io->info('No abandoned orders to clean.');
        } else {
            $io->info(\sprintf('Done: %d abandoned order(s) deleted.', $result['deleted']));
        }

        return Command::SUCCESS;
    }
}
