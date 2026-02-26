<?php

namespace App\Command;

use App\Entity\Loan;
use App\Entity\Penalty;
use App\Enum\LoanStatus;
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
    name: 'app:send-loan-sms-reminders',
    description: 'Envoie automatiquement les SMS Twilio (J-3 → retour, retard, pénalités).'
)]
final class SendLoanSmsRemindersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SmsReminderService $smsReminderService,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'N’envoie pas de SMS (log uniquement).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        $now = new \DateTimeImmutable();
        $today = $now->setTime(0, 0, 0);
        $maxDueDate = $today->modify('+3 days');

        $io->title('Rappels SMS – LIBRARYHUB');
        $io->text(sprintf('Date de référence : %s', $today->format('Y-m-d')));
        if ($dryRun) {
            $io->warning('Mode dry-run activé (aucun SMS ne sera envoyé).');
        }

        $counters = [
            'loans' => 0,
            'sms_sent' => 0,
            'sms_skipped' => 0,
            'penalty_sms_sent' => 0,
            'daily_sms_sent' => 0,
        ];

        $penaltiesToday = $this->entityManager->getRepository(Penalty::class)
            ->createQueryBuilder('p')
            ->innerJoin('p.loan', 'l')->addSelect('l')
            ->innerJoin('l.member', 'm')->addSelect('m')
            ->andWhere('p.issueDate = :today')
            ->andWhere('l.returnDate IS NULL')
            ->setParameter('today', \DateTime::createFromImmutable($today))
            ->orderBy('l.id', 'ASC')
            ->addOrderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();

        $notifiedLoans = [];
        $pendingFlush = 0;
        $batchSize = 25;

        foreach ($penaltiesToday as $penalty) {
            if (!$penalty instanceof Penalty) {
                continue;
            }

            $loan = $penalty->getLoan();
            $loanId = (int) ($loan?->getId() ?? 0);
            if ($loanId <= 0 || isset($notifiedLoans[$loanId])) {
                continue;
            }

            $notifiedLoans[$loanId] = true;

            $sent = $this->smsReminderService->sendPenaltyAppliedReminder($penalty, $today, $dryRun);
            if ($sent) {
                $counters['sms_sent']++;
                $counters['penalty_sms_sent']++;
                if (!$dryRun) {
                    $pendingFlush++;
                }
            }

            if ($pendingFlush >= $batchSize) {
                $this->entityManager->flush();
                $pendingFlush = 0;
            }
        }

        $loans = $this->entityManager->getRepository(Loan::class)
            ->createQueryBuilder('l')
            ->leftJoin('l.member', 'm')->addSelect('m')
            ->andWhere('l.returnDate IS NULL')
            ->andWhere('l.status IN (:statuses)')
            ->andWhere('l.dueDate <= :maxDue')
            ->setParameter('statuses', [LoanStatus::ACTIVE, LoanStatus::OVERDUE])
            ->setParameter('maxDue', \DateTime::createFromImmutable($maxDueDate))
            ->orderBy('l.dueDate', 'ASC')
            ->addOrderBy('l.id', 'ASC')
            ->getQuery()
            ->getResult();

        $io->text(sprintf('Emprunts sélectionnés (dueDate <= %s): %d', $maxDueDate->format('Y-m-d'), \count($loans)));

        foreach ($loans as $loan) {
            if (!$loan instanceof Loan) {
                continue;
            }

            $counters['loans']++;

            $sent = $this->smsReminderService->sendDailyLoanReminder($loan, $today, $dryRun);
            if ($sent) {
                $counters['sms_sent']++;
                $counters['daily_sms_sent']++;
                if (!$dryRun) {
                    $pendingFlush++;
                }
            } else {
                $counters['sms_skipped']++;
            }

            if ($pendingFlush >= $batchSize) {
                $this->entityManager->flush();
                $pendingFlush = 0;
            }
        }

        if (!$dryRun && $pendingFlush > 0) {
            $this->entityManager->flush();
        }

        $this->logger->info('Rappels SMS prêts/terminés.', $counters + [
            'date' => $today->format('Y-m-d'),
            'dry_run' => $dryRun,
        ]);

        $io->success(sprintf(
            'Terminé. SMS envoyés: %d (pénalités: %d, quotidiens: %d).',
            $counters['sms_sent'],
            $counters['penalty_sms_sent'],
            $counters['daily_sms_sent'],
        ));

        return Command::SUCCESS;
    }
}
