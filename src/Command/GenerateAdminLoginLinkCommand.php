<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AdminRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * Prints a magic login link for an admin. Meant for local development, where
 * MAILER_DSN is null:// and the emailed link can never arrive; also serves as
 * an emergency access path on the server (shell access required, which is a
 * stronger credential than the link itself).
 */
#[AsCommand(
    name: 'app:admin-login-link',
    description: 'Generate a magic login link for an admin account.',
)]
final class GenerateAdminLoginLinkCommand extends Command
{
    public function __construct(
        private readonly AdminRepository $adminRepository,
        // The generic alias needs an active request to pick a firewall; from
        // the CLI the admin firewall's handler must be targeted explicitly.
        #[Autowire(service: 'security.authenticator.login_link_handler.admin')]
        private readonly LoginLinkHandlerInterface $loginLinkHandler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email of the admin account');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $admin = $this->adminRepository->findOneBy(['email' => $email]);
        if ($admin === null) {
            $io->error(\sprintf('No admin account for "%s".', $email));

            return Command::FAILURE;
        }

        $io->writeln($this->loginLinkHandler->createLoginLink($admin)->getUrl());

        return Command::SUCCESS;
    }
}
