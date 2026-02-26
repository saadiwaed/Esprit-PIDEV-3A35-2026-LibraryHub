<?php

namespace App\Service;

use App\Entity\LoanRequest;
use App\Entity\User;
use App\Repository\BookCopyRepository;
use App\Repository\BookRepository;
use Psr\Log\LoggerInterface;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client as TwilioClient;

final class SmsNotifier
{
    public function __construct(
        private readonly TwilioClient $twilio,
        private readonly LoggerInterface $logger,
        private readonly BookRepository $bookRepository,
        private readonly BookCopyRepository $bookCopyRepository,
        private readonly string $fromPhoneNumber,
    ) {
    }

    public function sendLoanRequestStatusUpdate(LoanRequest $request): void
    {
        $now = new \DateTimeImmutable();
        $requestId = (int) ($request->getId() ?? 0);
        $status = strtoupper(trim((string) $request->getStatus()));

        if (!\in_array($status, [LoanRequest::STATUS_APPROVED, LoanRequest::STATUS_REJECTED], true)) {
            $this->logger->info('SMS demande d’emprunt ignoré : statut non décidé.', [
                'loan_request_id' => $requestId,
                'status' => $status,
            ]);

            return;
        }

        if ($this->wasSentToday($request->getLastSmsReminderSentAt(), $now)) {
            $this->logger->info('SMS demande d’emprunt ignoré : déjà envoyé aujourd’hui.', [
                'loan_request_id' => $requestId,
                'status' => $status,
            ]);

            return;
        }

        $member = $request->getMember();
        if (!$member instanceof User) {
            $this->logger->error('SMS demande d’emprunt ignoré : membre introuvable.', [
                'loan_request_id' => $requestId,
                'status' => $status,
            ]);

            return;
        }

        $to = $this->normalizeTnPhone($request->getPhoneNumber() ?: $member->getPhoneNumber());
        if ($to === null) {
            $this->logger->info('SMS demande d’emprunt ignoré : téléphone manquant ou invalide.', [
                'loan_request_id' => $requestId,
                'member_id' => $member->getId(),
                'status' => $status,
                'raw_phone_request' => $request->getPhoneNumber(),
                'raw_phone_member' => $member->getPhoneNumber(),
            ]);

            return;
        }

        $from = trim($this->fromPhoneNumber);
        if ($from === '') {
            $this->logger->error('SMS demande d’emprunt ignoré : TWILIO_PHONE_NUMBER non configuré.', [
                'loan_request_id' => $requestId,
                'member_id' => $member->getId(),
                'status' => $status,
            ]);

            return;
        }

        $bookId = (int) $request->getBookId();
        $bookTitle = $this->resolveBookTitle($bookId);
        $firstName = trim((string) $member->getFirstName());
        if ($firstName === '') {
            $firstName = 'cher membre';
        }

        $body = $status === LoanRequest::STATUS_APPROVED
            ? $this->buildApprovedMessage($firstName, $bookTitle, $request)
            : $this->buildRejectedMessage($firstName, $bookTitle, $request);

        try {
            $message = $this->twilio->messages->create($to, [
                'from' => $from,
                'body' => $body,
            ]);

            $request->setLastSmsReminderSentAt($now);

            $this->logger->info('SMS demande d’emprunt envoyé.', [
                'loan_request_id' => $requestId,
                'member_id' => $member->getId(),
                'to' => $to,
                'from' => $from,
                'status' => $status,
                'twilio_sid' => (string) ($message->sid ?? ''),
            ]);
        } catch (TwilioException $e) {
            $this->logger->error('Erreur Twilio (SMS demande d’emprunt).', [
                'loan_request_id' => $requestId,
                'member_id' => $member->getId(),
                'to' => $to,
                'from' => $from,
                'status' => $status,
                'exception' => $e,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur inattendue (SMS demande d’emprunt).', [
                'loan_request_id' => $requestId,
                'member_id' => $member->getId(),
                'to' => $to,
                'from' => $from,
                'status' => $status,
                'exception' => $e,
            ]);
        }
    }

    private function buildApprovedMessage(string $firstName, string $bookTitle, LoanRequest $request): string
    {
        $dueDate = $request->getDesiredReturnDate();
        $dueDateText = $dueDate instanceof \DateTimeInterface ? $dueDate->format('d/m/Y') : '—';

        return sprintf(
            "Bonjour %s, votre demande d'emprunt pour %s a été approuvée ! L'emprunt est actif jusqu'au %s. Merci. – LIBRARYHUB",
            $firstName,
            $bookTitle,
            $dueDateText
        );
    }

    private function buildRejectedMessage(string $firstName, string $bookTitle, LoanRequest $request): string
    {
        $reason = $this->resolveRefusalReason($request);
        $reasonPart = '';
        if ($reason !== '') {
            $reasonClean = rtrim($reason, ". \t\n\r\0\x0B");
            $reasonPart = sprintf(' Raison : %s.', $reasonClean);
        }

        return sprintf(
            "Bonjour %s, votre demande d'emprunt pour %s a été refusée.%s Merci de votre compréhension. – LIBRARYHUB",
            $firstName,
            $bookTitle,
            $reasonPart
        );
    }

    private function resolveBookTitle(int $bookId): string
    {
        if ($bookId <= 0) {
            return 'ce livre';
        }

        $book = $this->bookRepository->find($bookId);
        $title = $book?->getTitle();
        if (is_string($title) && trim($title) !== '') {
            return sprintf('« %s »', trim($title));
        }

        $bookCopy = $this->bookCopyRepository->find($bookId);
        if ($bookCopy !== null) {
            return sprintf('l’exemplaire #%d', (int) ($bookCopy->getId() ?? $bookId));
        }

        return sprintf('le livre #%d', $bookId);
    }

    private function resolveRefusalReason(LoanRequest $request): string
    {
        if (method_exists($request, 'getRefusalReason')) {
            $value = trim((string) $request->getRefusalReason());
            if ($value !== '') {
                return $value;
            }
        }

        $notes = (string) ($request->getNotes() ?? '');
        if ($notes === '') {
            return '';
        }

        $marker = 'Motif du refus:';
        $pos = strripos($notes, $marker);
        if ($pos === false) {
            return '';
        }

        return trim(substr($notes, $pos + strlen($marker)));
    }

    private function normalizeTnPhone(?string $raw): ?string
    {
        $value = preg_replace('/\s+/', '', trim((string) ($raw ?? '')));
        $value = str_replace(['-', '(', ')', '.'], '', $value);

        if ($value === '') {
            return null;
        }

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

    private function wasSentToday(?\DateTimeImmutable $sentAt, \DateTimeImmutable $now): bool
    {
        if (!$sentAt instanceof \DateTimeImmutable) {
            return false;
        }

        return $sentAt->setTime(0, 0, 0)->format('Y-m-d') === $now->setTime(0, 0, 0)->format('Y-m-d');
    }
}
