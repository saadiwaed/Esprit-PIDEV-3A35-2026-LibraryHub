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
    name: 'app:generate-overdue-penalties',
    description: 'Genere ou met a jour les penalites des emprunts en retard non retournes.',
)]
final class GenerateOverduePenaltiesCommand extends Command
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
        $updatedCount = $this->loanService->generateOrUpdateOverduePenalties($runAt);

        if ($updatedCount === 0) {
            $message = sprintf(
                'Aucune penalite a generer ou mettre a jour (%s).',
                $runAt->format('Y-m-d H:i:s')
            );
            $this->logger->info($message);
            $io->success($message);

            return Command::SUCCESS;
        }

        $message = sprintf(
            '%d penalite(s) impayee(s) ont ete generees/mises a jour (%s).',
            $updatedCount,
            $runAt->format('Y-m-d H:i:s')
        );
        $this->logger->info($message, ['updated_count' => $updatedCount]);
        $io->success($message);

        return Command::SUCCESS;
    }
}
