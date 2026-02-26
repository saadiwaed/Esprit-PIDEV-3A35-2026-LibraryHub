<?php

namespace App\Command;

use App\Entity\Loan;
use App\Repository\LoanRepository;
use App\Service\SmsReminderService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-due-date-reminders',
    description: 'Envoie des rappels SMS Twilio (J-3, J-1, J0) pour les emprunts actifs.'
)]
final class SendDueDateRemindersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoanRepository $loanRepository,
        private readonly SmsReminderService $smsReminderService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'N’envoie pas de SMS (log uniquement).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $now = new \DateTimeImmutable();
        $startOfDay = $now->setTime(0, 0, 0);

        $io->title('Rappels SMS échéance – LIBRARYHUB');
        $io->text(sprintf('Date de référence : %s', $startOfDay->format('Y-m-d')));
        if ($dryRun) {
            $io->warning('Mode dry-run activé (aucun SMS ne sera envoyé).');
        }

        $groups = [
            '3days' => $this->loanRepository->findDueSoonLoans(3, $startOfDay),
            '1day' => $this->loanRepository->findDueSoonLoans(1, $startOfDay),
            'today' => $this->loanRepository->findDueSoonLoans(0, $startOfDay),
        ];

        $io->section('Sélection');
        $io->listing([
            sprintf('J-3 : %d', \count($groups['3days'])),
            sprintf('J-1 : %d', \count($groups['1day'])),
            sprintf('J0  : %d', \count($groups['today'])),
        ]);

        $batchSize = 25;
        $pendingFlush = 0;

        $counters = [
            'candidates' => 0,
            'sent' => 0,
            'skipped_already_sent_today' => 0,
            'skipped_dry_run' => 0,
        ];

        foreach ($groups as $type => $loans) {
            foreach ($loans as $loan) {
                if (!$loan instanceof Loan) {
                    continue;
                }

                $counters['candidates']++;
                $loanId = (int) ($loan->getId() ?? 0);

                if ($this->wasSentToday($loan->getLastSmsSentAt(), $startOfDay)) {
                    $counters['skipped_already_sent_today']++;
                    $this->logger->info('Rappel SMS ignoré (déjà envoyé aujourd’hui).', [
                        'loan_id' => $loanId,
                        'type' => $type,
                        'date' => $startOfDay->format('Y-m-d'),
                    ]);
                    continue;
                }

                if ($dryRun) {
                    $counters['skipped_dry_run']++;
                    $this->logger->info('Dry-run : rappel SMS à envoyer.', [
                        'loan_id' => $loanId,
                        'type' => $type,
                        'due_date' => $loan->getDueDate()?->format('Y-m-d'),
                    ]);
                    continue;
                }

                $before = $loan->getLastSmsSentAt();
                $sent = $this->smsReminderService->sendDailyLoanReminder($loan, $startOfDay, $dryRun);
                $after = $loan->getLastSmsSentAt();

                if ($sent && !$this->wasSentToday($before, $startOfDay) && $this->wasSentToday($after, $startOfDay)) {
                    $counters['sent']++;
                    $pendingFlush++;
                }

                if ($pendingFlush >= $batchSize) {
                    $this->entityManager->flush();
                    $pendingFlush = 0;
                }
            }
        }

        if ($pendingFlush > 0) {
            $this->entityManager->flush();
        }

        $this->logger->info('Rappels SMS échéance terminés.', $counters + [
            'date' => $startOfDay->format('Y-m-d'),
        ]);

        $io->success(sprintf(
            'Terminé. SMS envoyés: %d (candidats: %d, déjà envoyés aujourd’hui: %d, dry-run: %d).',
            $counters['sent'],
            $counters['candidates'],
            $counters['skipped_already_sent_today'],
            $counters['skipped_dry_run'],
        ));

        return Command::SUCCESS;
    }

    private function wasSentToday(?\DateTimeInterface $lastSentAt, \DateTimeImmutable $startOfDay): bool
    {
        if (!$lastSentAt instanceof \DateTimeInterface) {
            return false;
        }

        return \DateTimeImmutable::createFromInterface($lastSentAt) >= $startOfDay;
    }
}
