<?php

namespace App\Command;

use App\Entity\Loan;
use App\Entity\LoanRequest;
use App\Entity\RenewalRequest;
use App\Entity\User;
use App\Repository\LoanRepository;
use App\Repository\LoanRequestRepository;
use App\Repository\RenewalRequestRepository;
use App\Service\LoanReminderService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-loan-reminders',
    description: 'Envoie les rappels email/SMS liés aux emprunts (échéance proche, retard, statuts de demandes).'
)]
final class SendLoanRemindersCommand extends Command
{
    private int $dueSoonDays;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoanRepository $loanRepository,
        private readonly LoanRequestRepository $loanRequestRepository,
        private readonly RenewalRequestRepository $renewalRequestRepository,
        private readonly LoanReminderService $reminderService,
        private readonly LoggerInterface $logger,
        int|string $dueSoonDays = 2,
    ) {
        parent::__construct();
        $this->dueSoonDays = (int) $dueSoonDays;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();
        $startOfDay = $now->setTime(0, 0, 0);

        $io->title('Envoi des rappels LIBRARYHUB');
        $io->text(sprintf('Date de référence : %s', $startOfDay->format('Y-m-d')));

        $memberLastEmailSentAtCache = [];
        $memberLastSmsSentAtCache = [];

        $counters = [
            'overdue_loans' => 0,
            'due_soon_loans' => 0,
            'loan_requests' => 0,
            'renewal_requests' => 0,
            'email_sent' => 0,
            'sms_sent' => 0,
            'email_skipped_daily_limit' => 0,
            'sms_skipped_daily_limit' => 0,
        ];

        $overdueLoans = $this->loanRepository->findOverdueOpenLoansForPenalty($startOfDay);
        $dueSoonLoans = $this->loanRepository->findDueSoonLoans($this->dueSoonDays, $startOfDay);

        $since = $startOfDay->modify('-2 days');
        $loanRequests = $this->loanRequestRepository->findRecentlyDecided($since);
        $renewalRequests = $this->renewalRequestRepository->findRecentlyDecided($since);

        $io->section('Sélection');
        $io->listing([
            sprintf('Emprunts en retard : %d', \count($overdueLoans)),
            sprintf('Emprunts à échéance proche (J-%d) : %d', $this->dueSoonDays, \count($dueSoonLoans)),
            sprintf('Demandes d’emprunt décidées (depuis %s) : %d', $since->format('Y-m-d'), \count($loanRequests)),
            sprintf('Demandes de renouvellement décidées (depuis %s) : %d', $since->format('Y-m-d'), \count($renewalRequests)),
        ]);

        $batchSize = 25;
        $pendingFlush = 0;

        foreach ($overdueLoans as $loan) {
            if (!$loan instanceof Loan) {
                continue;
            }

            $counters['overdue_loans']++;
            $member = $loan->getMember();
            if (!$member instanceof User) {
                continue;
            }

            $skipEmail = !$this->canSendMemberEmailToday($member, $startOfDay, $memberLastEmailSentAtCache);
            // Les SMS sont gérés par la commande dédiée app:send-loan-sms-reminders (évite les doublons).
            $skipSms = true;
            if ($skipEmail) {
                $counters['email_skipped_daily_limit']++;
            }
            if ($skipSms) {
                $counters['sms_skipped_daily_limit']++;
            }

            $res = $this->reminderService->sendOverdueReminder($loan, ['skip_email' => $skipEmail, 'skip_sms' => $skipSms]);

            if (($res['should_update_email_sent_at'] ?? false) === true) {
                $loan->setLastEmailReminderSentAt($now);
                $memberLastEmailSentAtCache[(int) $member->getId()] = $now;
                $counters['email_sent']++;
                $pendingFlush++;
            }
            if (($res['should_update_sms_sent_at'] ?? false) === true) {
                $loan->setLastSmsReminderSentAt($now);
                $memberLastSmsSentAtCache[(int) $member->getId()] = $now;
                $counters['sms_sent']++;
                $pendingFlush++;
            }

            if ($pendingFlush >= $batchSize) {
                $this->entityManager->flush();
                $pendingFlush = 0;
            }
        }

        foreach ($dueSoonLoans as $loan) {
            if (!$loan instanceof Loan) {
                continue;
            }

            $counters['due_soon_loans']++;
            $member = $loan->getMember();
            if (!$member instanceof User) {
                continue;
            }

            $skipEmail = !$this->canSendMemberEmailToday($member, $startOfDay, $memberLastEmailSentAtCache);
            if ($skipEmail) {
                $counters['email_skipped_daily_limit']++;
            }

            $res = $this->reminderService->sendDueSoonReminder($loan, ['skip_email' => $skipEmail, 'skip_sms' => true]);
            if (($res['should_update_email_sent_at'] ?? false) === true) {
                $loan->setLastEmailReminderSentAt($now);
                $memberLastEmailSentAtCache[(int) $member->getId()] = $now;
                $counters['email_sent']++;
                $pendingFlush++;
            }

            if ($pendingFlush >= $batchSize) {
                $this->entityManager->flush();
                $pendingFlush = 0;
            }
        }

        foreach ($loanRequests as $request) {
            if (!$request instanceof LoanRequest) {
                continue;
            }

            $counters['loan_requests']++;
            $member = $request->getMember();
            if (!$member instanceof User) {
                continue;
            }

            $skipEmail = !$this->canSendMemberEmailToday($member, $startOfDay, $memberLastEmailSentAtCache);
            if ($skipEmail) {
                $counters['email_skipped_daily_limit']++;
            }

            $res = $this->reminderService->sendRequestStatusUpdate($request, ['skip_email' => $skipEmail, 'skip_sms' => true]);
            if (($res['should_update_email_sent_at'] ?? false) === true) {
                $request->setLastEmailReminderSentAt($now);
                $memberLastEmailSentAtCache[(int) $member->getId()] = $now;
                $counters['email_sent']++;
                $pendingFlush++;
            }

            if ($pendingFlush >= $batchSize) {
                $this->entityManager->flush();
                $pendingFlush = 0;
            }
        }

        foreach ($renewalRequests as $request) {
            if (!$request instanceof RenewalRequest) {
                continue;
            }

            $counters['renewal_requests']++;
            $member = $request->getMember();
            if (!$member instanceof User) {
                continue;
            }

            $skipEmail = !$this->canSendMemberEmailToday($member, $startOfDay, $memberLastEmailSentAtCache);
            if ($skipEmail) {
                $counters['email_skipped_daily_limit']++;
            }

            $res = $this->reminderService->sendRenewalRequestStatusUpdate($request, ['skip_email' => $skipEmail, 'skip_sms' => true]);
            if (($res['should_update_email_sent_at'] ?? false) === true) {
                $request->setLastEmailReminderSentAt($now);
                $memberLastEmailSentAtCache[(int) $member->getId()] = $now;
                $counters['email_sent']++;
                $pendingFlush++;
            }

            if ($pendingFlush >= $batchSize) {
                $this->entityManager->flush();
                $pendingFlush = 0;
            }
        }

        if ($pendingFlush > 0) {
            $this->entityManager->flush();
        }

        $this->logger->info('Daily reminders done.', $counters + [
            'due_soon_days' => $this->dueSoonDays,
            'date' => $startOfDay->format('Y-m-d'),
        ]);

        $io->success(sprintf(
            'Terminé. Emails envoyés: %d, SMS envoyés: %d. (limites: email=%d, sms=%d)',
            $counters['email_sent'],
            $counters['sms_sent'],
            $counters['email_skipped_daily_limit'],
            $counters['sms_skipped_daily_limit'],
        ));

        return Command::SUCCESS;
    }

    /**
     * @param array<int, \DateTimeImmutable|null> $cache
     */
    private function canSendMemberEmailToday(User $member, \DateTimeImmutable $startOfDay, array &$cache): bool
    {
        $id = (int) ($member->getId() ?? 0);
        if ($id <= 0) {
            return false;
        }

        if (!\array_key_exists($id, $cache)) {
            $cache[$id] = $this->findMemberLastEmailReminderSentAt($member);
        }

        $last = $cache[$id];
        if (!$last instanceof \DateTimeImmutable) {
            return true;
        }

        return $last < $startOfDay;
    }

    /**
     * @param array<int, \DateTimeImmutable|null> $cache
     */
    private function canSendMemberSmsToday(User $member, \DateTimeImmutable $startOfDay, array &$cache): bool
    {
        $id = (int) ($member->getId() ?? 0);
        if ($id <= 0) {
            return false;
        }

        if (!\array_key_exists($id, $cache)) {
            $cache[$id] = $this->findMemberLastSmsReminderSentAt($member);
        }

        $last = $cache[$id];
        if (!$last instanceof \DateTimeImmutable) {
            return true;
        }

        return $last < $startOfDay;
    }

    private function findMemberLastEmailReminderSentAt(User $member): ?\DateTimeImmutable
    {
        $candidates = [
            $this->loanRepository->findMemberLastEmailReminderSentAt($member),
            $this->loanRequestRepository->findMemberLastEmailReminderSentAt($member),
            $this->renewalRequestRepository->findMemberLastEmailReminderSentAt($member),
        ];

        return $this->maxDateTime($candidates);
    }

    private function findMemberLastSmsReminderSentAt(User $member): ?\DateTimeImmutable
    {
        $candidates = [
            $this->loanRepository->findMemberLastSmsReminderSentAt($member),
            $this->loanRequestRepository->findMemberLastSmsReminderSentAt($member),
            $this->renewalRequestRepository->findMemberLastSmsReminderSentAt($member),
        ];

        return $this->maxDateTime($candidates);
    }

    /**
     * @param array<int, \DateTimeImmutable|null> $dates
     */
    private function maxDateTime(array $dates): ?\DateTimeImmutable
    {
        $max = null;
        foreach ($dates as $date) {
            if (!$date instanceof \DateTimeImmutable) {
                continue;
            }
            if (!$max instanceof \DateTimeImmutable || $date > $max) {
                $max = $date;
            }
        }

        return $max;
    }
}
