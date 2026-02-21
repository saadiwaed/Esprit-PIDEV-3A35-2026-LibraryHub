<?php

namespace App\Command;

use App\Service\LoanService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:loans:update-overdue-status',
    description: 'Met a jour les statuts des emprunts selon leurs dates limites.',
)]
final class UpdateOverdueLoansCommand extends Command
{
    public function __construct(
        private readonly LoanService $loanService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $runAt = new \DateTimeImmutable();
        $updatedCount = $this->loanService->refreshOverdueStatuses();

        if ($updatedCount === 0) {
            $message = sprintf(
                'Aucun emprunt a basculer en retard (%s).',
                $runAt->format('Y-m-d H:i:s')
            );
            $this->logger->info($message);
            $io->success($message);

            return Command::SUCCESS;
        }

        $message = sprintf(
            '%d emprunt(s) ont ete passes en statut OVERDUE (%s).',
            $updatedCount,
            $runAt->format('Y-m-d H:i:s')
        );
        $this->logger->info($message, ['updated_count' => $updatedCount]);
        $io->success($message);

        return Command::SUCCESS;
    }
}
