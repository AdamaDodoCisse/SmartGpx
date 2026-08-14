<?php

declare(strict_types=1);

namespace App\Identity\Command;

use App\Identity\Action\PromoteUserToAdminAction;
use App\Identity\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * ROLE_ADMIN ne s'accorde jamais depuis l'interface admin elle-même — uniquement via cette
 * commande, pour éviter toute surface d'auto-élévation de privilèges.
 */
#[AsCommand(name: 'app:user:promote-admin', description: 'Grants ROLE_ADMIN to an existing user by email.')]
final class PromoteUserToAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly PromoteUserToAdminAction $promoteUserToAdminAction,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Email of the user to promote.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        \assert(\is_string($email));

        $user = $this->userRepository->findOneByEmail($email);

        if (null === $user) {
            $io->error(sprintf('No user found with email "%s".', $email));

            return Command::FAILURE;
        }

        $this->promoteUserToAdminAction->execute($user);
        $io->success(sprintf('"%s" now has ROLE_ADMIN.', $email));

        return Command::SUCCESS;
    }
}
