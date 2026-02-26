<?php

namespace App\Service;

use App\Entity\Loan;
use App\Entity\Penalty;
use App\Entity\User;
use App\Enum\PaymentStatus;
use Psr\Log\LoggerInterface;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client as TwilioClient;

final class SmsReminderService
{
    public function __construct(
        private readonly TwilioClient $twilio,
        private readonly LoggerInterface $logger,
        private readonly string $fromPhoneNumber,
    ) {
    }

    /**
     * Envoie un SMS quotidien (à partir de J-3 inclus, puis tous les jours jusqu'au retour).
     * Retourne true si un SMS a été envoyé et les champs Loan ont été mis à jour en mémoire.
     */
    public function sendDailyLoanReminder(Loan $loan, \DateTimeImmutable $today, bool $dryRun = false): bool
    {
        $today = $today->setTime(0, 0, 0);
        $loanId = (int) ($loan->getId() ?? 0);

        if ($loan->getReturnDate() instanceof \DateTimeInterface) {
            $this->logger->info('Rappel SMS ignoré : emprunt déjà retourné.', ['loan_id' => $loanId]);

            return false;
        }

        $dueDate = $loan->getDueDate() instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($loan->getDueDate())->setTime(0, 0, 0)
            : null;

        if (!$dueDate instanceof \DateTimeImmutable) {
            $this->logger->warning('Rappel SMS ignoré : date limite manquante.', ['loan_id' => $loanId]);

            return false;
        }

        $daysUntilDue = (int) $today->diff($dueDate)->format('%r%a');

        if ($daysUntilDue > 3) {
            return false;
        }

        if ($this->wasSentToday($loan->getLastSmsSentAt(), $today)) {
            $this->logger->info('Rappel SMS ignoré : déjà envoyé aujourd’hui.', ['loan_id' => $loanId, 'due_date' => $dueDate->format('Y-m-d')]);

            return false;
        }

        if ($daysUntilDue === 0) {
            return $this->sendDueDateWarning($loan, $dueDate, $today, $dryRun);
        }

        if ($daysUntilDue >= 0) {
            return $this->sendCountdownReminder($loan, $dueDate, $daysUntilDue, $today, $dryRun);
        }

        $daysLate = abs($daysUntilDue);
        $unpaid = $loan->getTotalUnpaidPenalty();

        return $this->sendOverdueReminder($loan, $daysLate, $unpaid, $today, $dryRun);
    }

    /**
     * Envoie un SMS immédiat lors de l'application/création d'une pénalité.
     * Retourne true si un SMS a été envoyé et les champs Loan ont été mis à jour en mémoire.
     */
    public function sendPenaltyAppliedReminder(Penalty $penalty, \DateTimeImmutable $today, bool $dryRun = false): bool
    {
        $today = $today->setTime(0, 0, 0);

        $loan = $penalty->getLoan();
        $loanId = (int) ($loan?->getId() ?? 0);
        if (!$loan instanceof Loan) {
            $this->logger->warning('SMS pénalité ignoré : emprunt manquant.', ['penalty_id' => $penalty->getId()]);

            return false;
        }

        if ($loan->getReturnDate() instanceof \DateTimeInterface) {
            $this->logger->info('SMS pénalité ignoré : emprunt déjà retourné.', ['loan_id' => $loanId, 'penalty_id' => $penalty->getId()]);

            return false;
        }

        if ($penalty->isWaived()) {
            $this->logger->info('SMS pénalité ignoré : pénalité exonérée.', ['loan_id' => $loanId, 'penalty_id' => $penalty->getId()]);

            return false;
        }

        if ($penalty->getStatus() === PaymentStatus::PAID) {
            $this->logger->info('SMS pénalité ignoré : pénalité déjà payée.', ['loan_id' => $loanId, 'penalty_id' => $penalty->getId()]);

            return false;
        }

        if ($this->wasSentToday($loan->getPenaltyLastNotifiedAt(), $today)) {
            return false;
        }

        if ($this->wasSentToday($loan->getLastSmsSentAt(), $today)) {
            $this->logger->info('SMS pénalité ignoré : un SMS a déjà été envoyé aujourd’hui pour cet emprunt.', [
                'loan_id' => $loanId,
                'penalty_id' => $penalty->getId(),
            ]);

            return false;
        }

        $member = $loan->getMember();
        if (!$member instanceof User) {
            $this->logger->warning('SMS pénalité ignoré : adhérent manquant.', ['loan_id' => $loanId, 'penalty_id' => $penalty->getId()]);

            return false;
        }

        $to = $this->normalizeTunisianPhone($this->resolvePhoneNumber($loan, $member));
        if ($to === null) {
            $this->logger->warning('SMS pénalité ignoré : téléphone invalide.', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'penalty_id' => $penalty->getId(),
                'raw_phone' => $this->resolvePhoneNumber($loan, $member),
            ]);

            return false;
        }

        $from = trim($this->fromPhoneNumber);
        if ($from === '') {
            $this->logger->error('SMS pénalité ignoré : Twilio expéditeur non configuré.', ['loan_id' => $loanId, 'penalty_id' => $penalty->getId()]);

            return false;
        }

        $firstName = trim((string) ($member->getFirstName() ?? ''));
        $greeting = $firstName !== '' ? sprintf('Bonjour %s,', $firstName) : 'Bonjour,';
        $title = $this->resolveBookTitle($loan);
        $amount = number_format($penalty->getAmount(), 2, ',', ' ');
        $days = (int) $penalty->getLateDays();
        $reason = trim($penalty->getReasonLabel());

        $body = sprintf(
            '%s une pénalité de %s TND a été appliquée pour retard de %d jours sur %s. Raison : %s. – LIBRARYHUB',
            $greeting,
            $amount,
            $days,
            $title,
            $reason !== '' ? $reason : '—'
        );

        if ($dryRun) {
            $this->logger->info('Dry-run SMS pénalité : envoi simulé.', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'penalty_id' => $penalty->getId(),
                'to' => $to,
                'body' => $body,
            ]);

            return true;
        }

        try {
            $message = $this->twilio->messages->create($to, [
                'from' => $from,
                'body' => $body,
            ]);

            $now = new \DateTimeImmutable();
            $loan->setPenaltyLastNotifiedAt($now);
            $loan->setLastSmsSentAt($now);

            $this->logger->info('SMS pénalité envoyé.', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'penalty_id' => $penalty->getId(),
                'to' => $to,
                'from' => $from,
                'sid' => $message->sid ?? null,
            ]);

            return true;
        } catch (TwilioException $e) {
            $this->logger->error('Erreur Twilio (SMS pénalité).', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'penalty_id' => $penalty->getId(),
                'to' => $to,
                'error_message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur inattendue (SMS pénalité).', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'penalty_id' => $penalty->getId(),
                'to' => $to,
                'exception' => $e::class,
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendCountdownReminder(
        Loan $loan,
        \DateTimeImmutable $dueDate,
        int $daysUntilDue,
        \DateTimeImmutable $today,
        bool $dryRun,
    ): bool {
        $loanId = (int) ($loan->getId() ?? 0);
        $member = $loan->getMember();
        if (!$member instanceof User) {
            $this->logger->warning('Rappel SMS ignoré : adhérent manquant.', ['loan_id' => $loanId]);

            return false;
        }

        $to = $this->normalizeTunisianPhone($this->resolvePhoneNumber($loan, $member));
        if ($to === null) {
            $this->logger->warning('Rappel SMS ignoré : téléphone invalide.', ['loan_id' => $loanId, 'member_id' => $member->getId(), 'raw_phone' => $this->resolvePhoneNumber($loan, $member)]);

            return false;
        }

        $from = trim($this->fromPhoneNumber);
        if ($from === '') {
            $this->logger->error('Rappel SMS ignoré : Twilio expéditeur non configuré.', ['loan_id' => $loanId]);

            return false;
        }

        $firstName = trim((string) ($member->getFirstName() ?? ''));
        $greeting = $firstName !== '' ? sprintf('Bonjour %s,', $firstName) : 'Bonjour,';
        $title = $this->resolveBookTitle($loan);
        $dueDateFr = $dueDate->format('d/m/Y');

        $body = sprintf(
            '%s votre emprunt %s arrive à échéance dans %d jours (le %s). Merci de le retourner à temps. – LIBRARYHUB',
            $greeting,
            $title,
            max(1, $daysUntilDue),
            $dueDateFr
        );

        if ($dryRun) {
            $this->logger->info('Dry-run rappel SMS (compte à rebours) : envoi simulé.', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'body' => $body,
            ]);

            return true;
        }

        try {
            $message = $this->twilio->messages->create($to, [
                'from' => $from,
                'body' => $body,
            ]);

            $loan->setLastSmsSentAt(new \DateTimeImmutable());

            $this->logger->info('Rappel SMS (compte à rebours) envoyé.', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'sid' => $message->sid ?? null,
                'days_until_due' => $daysUntilDue,
                'due_date' => $dueDate->format('Y-m-d'),
            ]);

            return true;
        } catch (TwilioException $e) {
            $this->logger->error('Erreur Twilio (rappel SMS compte à rebours).', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'error_message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur inattendue (rappel SMS compte à rebours).', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'exception' => $e::class,
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendDueDateWarning(Loan $loan, \DateTimeImmutable $dueDate, \DateTimeImmutable $today, bool $dryRun): bool
    {
        $loanId = (int) ($loan->getId() ?? 0);
        $member = $loan->getMember();
        if (!$member instanceof User) {
            $this->logger->warning('Rappel SMS ignoré : adhérent manquant.', ['loan_id' => $loanId]);

            return false;
        }

        $to = $this->normalizeTunisianPhone($this->resolvePhoneNumber($loan, $member));
        if ($to === null) {
            $this->logger->warning('Rappel SMS ignoré : téléphone invalide.', ['loan_id' => $loanId, 'member_id' => $member->getId(), 'raw_phone' => $this->resolvePhoneNumber($loan, $member)]);

            return false;
        }

        $from = trim($this->fromPhoneNumber);
        if ($from === '') {
            $this->logger->error('Rappel SMS ignoré : Twilio expéditeur non configuré.', ['loan_id' => $loanId]);

            return false;
        }

        $firstName = trim((string) ($member->getFirstName() ?? ''));
        $greeting = $firstName !== '' ? sprintf('Bonjour %s,', $firstName) : 'Bonjour,';
        $title = $this->resolveBookTitle($loan);

        $body = sprintf(
            '%s aujourd\'hui est la date limite pour %s. Pénalité appliquée si retard. – LIBRARYHUB',
            $greeting,
            $title
        );

        if ($dryRun) {
            $this->logger->info('Dry-run rappel SMS (jour J) : envoi simulé.', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'body' => $body,
            ]);

            return true;
        }

        try {
            $message = $this->twilio->messages->create($to, [
                'from' => $from,
                'body' => $body,
            ]);

            $loan->setLastSmsSentAt(new \DateTimeImmutable());

            $this->logger->info('Rappel SMS (jour J) envoyé.', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'sid' => $message->sid ?? null,
                'due_date' => $dueDate->format('Y-m-d'),
            ]);

            return true;
        } catch (TwilioException $e) {
            $this->logger->error('Erreur Twilio (rappel SMS jour J).', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'error_message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur inattendue (rappel SMS jour J).', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'exception' => $e::class,
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function sendOverdueReminder(Loan $loan, int $daysLate, float $unpaidPenalty, \DateTimeImmutable $today, bool $dryRun): bool
    {
        $loanId = (int) ($loan->getId() ?? 0);
        $member = $loan->getMember();
        if (!$member instanceof User) {
            $this->logger->warning('Rappel SMS ignoré : adhérent manquant.', ['loan_id' => $loanId]);

            return false;
        }

        $to = $this->normalizeTunisianPhone($this->resolvePhoneNumber($loan, $member));
        if ($to === null) {
            $this->logger->warning('Rappel SMS ignoré : téléphone invalide.', ['loan_id' => $loanId, 'member_id' => $member->getId(), 'raw_phone' => $this->resolvePhoneNumber($loan, $member)]);

            return false;
        }

        $from = trim($this->fromPhoneNumber);
        if ($from === '') {
            $this->logger->error('Rappel SMS ignoré : Twilio expéditeur non configuré.', ['loan_id' => $loanId]);

            return false;
        }

        $firstName = trim((string) ($member->getFirstName() ?? ''));
        $greeting = $firstName !== '' ? sprintf('Bonjour %s,', $firstName) : 'Bonjour,';
        $title = $this->resolveBookTitle($loan);
        $amount = number_format(max(0, $unpaidPenalty), 2, ',', ' ');

        $body = sprintf(
            '%s votre emprunt %s est en retard de %d jours. Pénalité actuelle : %s TND. Merci de régulariser. – LIBRARYHUB',
            $greeting,
            $title,
            max(1, $daysLate),
            $amount
        );

        if ($dryRun) {
            $this->logger->info('Dry-run rappel SMS (retard) : envoi simulé.', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'body' => $body,
                'days_late' => $daysLate,
                'unpaid_penalty' => $unpaidPenalty,
            ]);

            return true;
        }

        try {
            $message = $this->twilio->messages->create($to, [
                'from' => $from,
                'body' => $body,
            ]);

            $loan->setLastSmsSentAt(new \DateTimeImmutable());

            $this->logger->info('Rappel SMS (retard) envoyé.', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'sid' => $message->sid ?? null,
                'days_late' => $daysLate,
                'unpaid_penalty' => $unpaidPenalty,
            ]);

            return true;
        } catch (TwilioException $e) {
            $this->logger->error('Erreur Twilio (rappel SMS retard).', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'error_message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Erreur inattendue (rappel SMS retard).', [
                'loan_id' => $loanId,
                'member_id' => $member->getId(),
                'to' => $to,
                'exception' => $e::class,
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveBookTitle(Loan $loan): string
    {
        if (method_exists($loan, 'getBook')) {
            $book = $loan->getBook();
            if ($book !== null && method_exists($book, 'getTitle')) {
                $title = trim((string) ($book->getTitle() ?? ''));
                if ($title !== '') {
                    return $title;
                }
            }
        }

        $bookCopy = $loan->getBookCopy();
        if ($bookCopy !== null && method_exists($bookCopy, 'getBook')) {
            $book = $bookCopy->getBook();
            if ($book !== null && method_exists($book, 'getTitle')) {
                $title = trim((string) ($book->getTitle() ?? ''));
                if ($title !== '') {
                    return $title;
                }
            }
        }

        $copyId = (int) ($bookCopy?->getId() ?? 0);
        if ($copyId > 0) {
            return sprintf('le livre n°%d', $copyId);
        }

        return 'votre livre';
    }

    private function resolvePhoneNumber(Loan $loan, User $member): ?string
    {
        $value = trim((string) ($member->getPhoneNumber() ?? ''));
        if ($value !== '') {
            return $value;
        }

        $loanPhone = method_exists($loan, 'getPhoneNumber') ? trim((string) ($loan->getPhoneNumber() ?? '')) : '';
        if ($loanPhone !== '') {
            return $loanPhone;
        }

        if (method_exists($member, 'getPhone')) {
            $fallback = trim((string) ($member->getPhone() ?? ''));
            if ($fallback !== '') {
                return $fallback;
            }
        }

        return null;
    }

    private function normalizeTunisianPhone(?string $raw): ?string
    {
        $value = preg_replace('/\\s+/', '', trim((string) ($raw ?? '')));
        if ($value === '') {
            return null;
        }

        $value = str_replace(['-', '(', ')', '.'], '', $value);

        if (preg_match('/^\\+216\\d{8}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^216(\\d{8})$/', $value, $m) === 1) {
            return '+216' . $m[1];
        }

        if (preg_match('/^(\\d{8})$/', $value, $m) === 1) {
            return '+216' . $m[1];
        }

        return null;
    }

    private function wasSentToday(?\DateTimeInterface $lastSentAt, \DateTimeImmutable $today): bool
    {
        if (!$lastSentAt instanceof \DateTimeInterface) {
            return false;
        }

        return \DateTimeImmutable::createFromInterface($lastSentAt) >= $today->setTime(0, 0, 0);
    }
}
