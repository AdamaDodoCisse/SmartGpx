<?php

declare(strict_types=1);

namespace App\Billing\Command;

use App\Billing\Action\SeedCreditPackLaunchGridAction;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * À exécuter une fois après `doctrine:schema:update --force` sur une base fraîche (dev ou test) —
 * remplace l'ancienne migration de seed. Idempotent, voir SeedCreditPackLaunchGridAction.
 */
#[AsCommand(name: 'app:credit-pack:seed-launch-grid', description: 'Seeds the credit_pack launch grid on a fresh database.')]
final class SeedCreditPackLaunchGridCommand extends Command
{
    public function __construct(private readonly SeedCreditPackLaunchGridAction $seedCreditPackLaunchGridAction)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->seedCreditPackLaunchGridAction->execute();
        (new SymfonyStyle($input, $output))->success('Credit pack launch grid seeded (no-op if packs already exist).');

        return Command::SUCCESS;
    }
}
