<?php

namespace App\Command;

use App\Entity\Penalty;
use App\Enum\PaymentStatus;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:accrue-daily-late-fees',
    description: 'Ajoute ou met a jour les penalites journalieres pour les emprunts en retard',
)]
final class AccrueDailyLateFeesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoanRepository $loanRepository,
        private readonly PenaltyRepository $penaltyRepository,
        private readonly LoggerInterface $logger,
        private readonly float $dailyLateFeeRate,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $today = new \DateTimeImmutable('today');

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $processed = 0;
        $dirty = 0;
        $batchSize = 50;

        $query = $this->loanRepository
            ->findCurrentlyOverdueUnreturned()
            ->getQuery();

        foreach ($query->toIterable() as $loan) {
            ++$processed;

            if ($loan->getReturnDate() instanceof \DateTimeInterface) {
                ++$skipped;
                continue;
            }

            $dueDate = $loan->getDueDate();
            if (!$dueDate instanceof \DateTimeInterface) {
                ++$skipped;
                $this->logger->warning('Emprunt ignore: date limite manquante.', ['loan_id' => $loan->getId()]);
                continue;
            }

            $expectedLateDays = (int) $loan->getDaysLate($today);

            $penalty = $this->penaltyRepository->findActiveLatePenaltyForLoan($loan);

            if (!$penalty instanceof Penalty) {
                $latestLatePenalty = $this->penaltyRepository->findLatestLatePenaltyForLoan($loan);
                if (
                    $latestLatePenalty instanceof Penalty
                    && ($latestLatePenalty->isWaived() || $latestLatePenalty->getStatus() !== PaymentStatus::UNPAID)
                ) {
                    ++$skipped;
                    continue;
                }

                $penalty = (new Penalty())
                    ->setLoan($loan)
                    ->setReason(sprintf('%s - Retard de %d jours', Penalty::DAILY_LATE_REASON_PREFIX, $expectedLateDays))
                    ->setDailyRate($this->dailyLateFeeRate)
                    ->setLateDays($expectedLateDays)
                    ->setAmount(round($expectedLateDays * $this->dailyLateFeeRate, 2))
                    ->setIssueDate(\DateTime::createFromImmutable($today))
                    ->setStatus(PaymentStatus::UNPAID)
                    ->setWaived(false)
                    ->setNotes(null);

                $this->entityManager->persist($penalty);
                ++$created;
                ++$dirty;
            } else {
                $rate = $penalty->getDailyRate() > 0 ? $penalty->getDailyRate() : $this->dailyLateFeeRate;

                if ($penalty->getDailyRate() <= 0) {
                    $penalty->setDailyRate($rate);
                }

                if ($penalty->getLateDays() < $expectedLateDays) {
                    $penalty->setLateDays($expectedLateDays);
                    $penalty->setAmount(round($expectedLateDays * $rate, 2));
                    if ($penalty->isDailyLateFee()) {
                        $penalty->setReason(sprintf('%s - Retard de %d jours', Penalty::DAILY_LATE_REASON_PREFIX, $expectedLateDays));
                    }
                    ++$updated;
                    ++$dirty;
                } else {
                    ++$skipped;
                }
            }

            if ($dirty > 0 && ($dirty % $batchSize) === 0) {
                $this->entityManager->flush();
            }
        }

        if ($dirty > 0) {
            $this->entityManager->flush();
        }

        $message = sprintf(
            'Penalites journalieres: %d creee(s), %d mise(s) a jour, %d ignoree(s), %d emprunt(s) traite(s).',
            $created,
            $updated,
            $skipped,
            $processed
        );

        $this->logger->info($message, [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'processed' => $processed,
            'date' => $today->format('Y-m-d'),
            'daily_rate' => $this->dailyLateFeeRate,
        ]);

        $io->success($message);

        return Command::SUCCESS;
    }
}
