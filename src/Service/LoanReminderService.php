<?php

namespace App\Service;

use App\Entity\Loan;
use App\Entity\LoanRequest;
use App\Entity\Penalty;
use App\Entity\RenewalRequest;
use App\Entity\User;
use Brevo\Brevo;
use Brevo\Exceptions\BrevoApiException;
use Brevo\Exceptions\BrevoException;
use Brevo\TransactionalEmails\Requests\SendTransacEmailRequest;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestSender;
use Brevo\TransactionalEmails\Types\SendTransacEmailRequestToItem;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client as TwilioClient;

final class LoanReminderService
{
    /**
     * @var array{email_sent: bool, sms_sent: bool, email_error: string|null, sms_error: string|null}
     */
    public const RESULT_SHAPE = [
        'email_sent' => false,
        'sms_sent' => false,
        'email_error' => null,
        'sms_error' => null,
    ];

    private int $dueSoonDays;
    private int $smsOverdueDaysThreshold;
    private float $smsPenaltyThreshold;

    public function __construct(
        private readonly Brevo $brevo,
        private readonly TwilioClient $twilio,
        private readonly Environment $twig,
        private readonly RouterInterface $router,
        private readonly LoggerInterface $logger,
        private readonly string $brevoApiKey,
        private readonly string $brevoSenderEmail,
        private readonly string $brevoSenderName,
        private readonly string $twilioSid,
        private readonly string $twilioAuthToken,
        private readonly string $twilioFromPhoneNumber,
        private readonly string $publicBaseUrl,
        int|string $dueSoonDays = 2,
        int|string $smsOverdueDaysThreshold = 3,
        float|string $smsPenaltyThreshold = 5.0,
    ) {
        $this->dueSoonDays = (int) $dueSoonDays;
        $this->smsOverdueDaysThreshold = (int) $smsOverdueDaysThreshold;
        $this->smsPenaltyThreshold = (float) $smsPenaltyThreshold;
    }

    /**
     * @return array{email_sent: bool, sms_sent: bool, email_error: string|null, sms_error: string|null, should_update_email_sent_at: bool, should_update_sms_sent_at: bool}
     */
    public function sendOverdueReminder(Loan $loan, array $options = []): array
    {
        $now = new \DateTimeImmutable();
        $loanId = (int) ($loan->getId() ?? 0);
        $member = $loan->getMember();
        $daysLate = $loan->getDaysLate($now);
        $totalUnpaidPenalty = $loan->getTotalUnpaidPenalty();

        $result = self::RESULT_SHAPE + [
            'should_update_email_sent_at' => false,
            'should_update_sms_sent_at' => false,
        ];

        if (!$member instanceof User) {
            $this->logger->error('Reminder skipped: loan has no member.', ['loan_id' => $loanId]);

            return $result;
        }

        if ($daysLate <= 0) {
            $this->logger->info('Overdue reminder skipped: not late.', ['loan_id' => $loanId, 'days_late' => $daysLate]);

            return $result;
        }

        $urgentSms = $daysLate >= $this->smsOverdueDaysThreshold || $totalUnpaidPenalty >= $this->smsPenaltyThreshold;

        if (($options['skip_sms'] ?? false) !== true && $urgentSms && !$this->wasSentToday($loan->getLastSmsReminderSentAt(), $now)) {
            $smsBody = sprintf(
                'Votre emprunt #%d est en retard de %d jour(s). Pénalité : %s TND. Merci de régulariser.',
                $loanId,
                $daysLate,
                number_format($totalUnpaidPenalty, 2, ',', ' ')
            );
            $smsSend = $this->sendSms($member, $smsBody, ['loan_id' => $loanId, 'days_late' => $daysLate, 'total_unpaid_penalty' => $totalUnpaidPenalty]);
            $result['sms_sent'] = $smsSend['sent'];
            $result['sms_error'] = $smsSend['error'];
            $result['should_update_sms_sent_at'] = $smsSend['sent'];
        }

        if (($options['skip_email'] ?? false) !== true && !$this->wasSentToday($loan->getLastEmailReminderSentAt(), $now)) {
            $subject = sprintf('LIBRARYHUB – Emprunt #%d en retard (%d jour(s))', $loanId, $daysLate);
            $emailSend = $this->sendEmail(
                $member,
                $subject,
                'emails/loan_overdue.html.twig',
                [
                    'loan' => $loan,
                    'daysLate' => $daysLate,
                    'totalUnpaidPenalty' => $totalUnpaidPenalty,
                    'myLoansUrl' => $this->generateAbsoluteUrl('member_loans'),
                ],
                ['loan_id' => $loanId, 'days_late' => $daysLate, 'total_unpaid_penalty' => $totalUnpaidPenalty]
            );
            $result['email_sent'] = $emailSend['sent'];
            $result['email_error'] = $emailSend['error'];
            $result['should_update_email_sent_at'] = $emailSend['sent'];
        }

        return $result;
    }

    /**
     * @return array{email_sent: bool, sms_sent: bool, email_error: string|null, sms_error: string|null, should_update_email_sent_at: bool, should_update_sms_sent_at: bool}
     */
    public function sendDueSoonReminder(Loan $loan, array $options = []): array
    {
        $now = new \DateTimeImmutable();
        $loanId = (int) ($loan->getId() ?? 0);
        $member = $loan->getMember();

        $result = self::RESULT_SHAPE + [
            'should_update_email_sent_at' => false,
            'should_update_sms_sent_at' => false,
        ];

        if (!$member instanceof User) {
            $this->logger->error('Reminder skipped: loan has no member.', ['loan_id' => $loanId]);

            return $result;
        }

        $dueDate = $loan->getDueDate() instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($loan->getDueDate())->setTime(0, 0, 0)
            : null;

        if (!$dueDate instanceof \DateTimeImmutable) {
            $this->logger->error('Due-soon reminder skipped: missing dueDate.', ['loan_id' => $loanId]);

            return $result;
        }

        if ($loan->getReturnDate() instanceof \DateTimeInterface) {
            $this->logger->info('Due-soon reminder skipped: already returned.', ['loan_id' => $loanId]);

            return $result;
        }

        if (($options['skip_email'] ?? false) !== true && !$this->wasSentToday($loan->getLastEmailReminderSentAt(), $now)) {
            $subject = sprintf('LIBRARYHUB – Rappel : retour prévu le %s (emprunt #%d)', $dueDate->format('d/m/Y'), $loanId);
            $emailSend = $this->sendEmail(
                $member,
                $subject,
                'emails/loan_due_soon.html.twig',
                [
                    'loan' => $loan,
                    'dueDate' => $dueDate,
                    'myLoansUrl' => $this->generateAbsoluteUrl('member_loans'),
                ],
                ['loan_id' => $loanId, 'due_date' => $dueDate->format('Y-m-d')]
            );
            $result['email_sent'] = $emailSend['sent'];
            $result['email_error'] = $emailSend['error'];
            $result['should_update_email_sent_at'] = $emailSend['sent'];
        }

        return $result;
    }

    /**
     * @return array{email_sent: bool, sms_sent: bool, email_error: string|null, sms_error: string|null, should_update_email_sent_at: bool, should_update_sms_sent_at: bool}
     */
    public function sendRequestStatusUpdate(LoanRequest $request, array $options = []): array
    {
        $now = new \DateTimeImmutable();
        $requestId = (int) ($request->getId() ?? 0);
        $member = $request->getMember();

        $result = self::RESULT_SHAPE + [
            'should_update_email_sent_at' => false,
            'should_update_sms_sent_at' => false,
        ];

        if (!$member instanceof User) {
            $this->logger->error('LoanRequest reminder skipped: missing member.', ['loan_request_id' => $requestId]);

            return $result;
        }

        $status = strtoupper(trim((string) $request->getStatus()));
        if (!\in_array($status, [LoanRequest::STATUS_APPROVED, LoanRequest::STATUS_REJECTED], true)) {
            $this->logger->info('LoanRequest reminder skipped: status not decided.', ['loan_request_id' => $requestId, 'status' => $status]);

            return $result;
        }

        if (($options['skip_email'] ?? false) === true || $this->wasSentToday($request->getLastEmailReminderSentAt(), $now)) {
            return $result;
        }

        $subject = $status === LoanRequest::STATUS_APPROVED
            ? sprintf('LIBRARYHUB – Votre demande d’emprunt #%d a été approuvée', $requestId)
            : sprintf('LIBRARYHUB – Votre demande d’emprunt #%d a été refusée', $requestId);

        $emailSend = $this->sendEmail(
            $member,
            $subject,
            'emails/loan_request_status.html.twig',
            [
                'request' => $request,
                'status' => $status,
                'myLoansUrl' => $this->generateAbsoluteUrl('member_loans', ['tab' => 'request']),
            ],
            ['loan_request_id' => $requestId, 'status' => $status]
        );

        $result['email_sent'] = $emailSend['sent'];
        $result['email_error'] = $emailSend['error'];
        $result['should_update_email_sent_at'] = $emailSend['sent'];

        return $result;
    }

    /**
     * @return array{email_sent: bool, sms_sent: bool, email_error: string|null, sms_error: string|null, should_update_email_sent_at: bool, should_update_sms_sent_at: bool}
     */
    public function sendRenewalRequestStatusUpdate(RenewalRequest $request, array $options = []): array
    {
        $now = new \DateTimeImmutable();
        $requestId = (int) ($request->getId() ?? 0);
        $member = $request->getMember();

        $result = self::RESULT_SHAPE + [
            'should_update_email_sent_at' => false,
            'should_update_sms_sent_at' => false,
        ];

        if (!$member instanceof User) {
            $this->logger->error('RenewalRequest reminder skipped: missing member.', ['renewal_request_id' => $requestId]);

            return $result;
        }

        $status = strtoupper(trim((string) $request->getStatus()));
        if (!\in_array($status, [RenewalRequest::STATUS_APPROVED, RenewalRequest::STATUS_REJECTED], true)) {
            $this->logger->info('RenewalRequest reminder skipped: status not decided.', ['renewal_request_id' => $requestId, 'status' => $status]);

            return $result;
        }

        if (($options['skip_email'] ?? false) === true || $this->wasSentToday($request->getLastEmailReminderSentAt(), $now)) {
            return $result;
        }

        $loanId = (int) ($request->getLoan()?->getId() ?? 0);
        $subject = $status === RenewalRequest::STATUS_APPROVED
            ? sprintf('LIBRARYHUB – Renouvellement approuvé (emprunt #%d)', $loanId)
            : sprintf('LIBRARYHUB – Renouvellement refusé (emprunt #%d)', $loanId);

        $emailSend = $this->sendEmail(
            $member,
            $subject,
            'emails/renewal_request_status.html.twig',
            [
                'request' => $request,
                'status' => $status,
                'myLoansUrl' => $this->generateAbsoluteUrl('member_loans'),
            ],
            ['renewal_request_id' => $requestId, 'loan_id' => $loanId, 'status' => $status]
        );

        $result['email_sent'] = $emailSend['sent'];
        $result['email_error'] = $emailSend['error'];
        $result['should_update_email_sent_at'] = $emailSend['sent'];

        return $result;
    }

    /**
     * @return array{email_sent: bool, sms_sent: bool, email_error: string|null, sms_error: string|null, should_update_email_sent_at: bool, should_update_sms_sent_at: bool}
     */
    public function sendPenaltyUpdate(Penalty $penalty, string $event = 'updated', array $options = []): array
    {
        $now = new \DateTimeImmutable();
        $penaltyId = (int) ($penalty->getId() ?? 0);
        $loan = $penalty->getLoan();
        $loanId = (int) ($loan?->getId() ?? 0);
        $member = $loan?->getMember();

        $result = self::RESULT_SHAPE + [
            'should_update_email_sent_at' => false,
            'should_update_sms_sent_at' => false,
        ];

        if (!$loan instanceof Loan || !$member instanceof User) {
            $this->logger->error('Penalty reminder skipped: missing loan/member.', ['penalty_id' => $penaltyId, 'loan_id' => $loanId]);

            return $result;
        }

        $totalUnpaidPenalty = $loan->getTotalUnpaidPenalty();

        if (($options['skip_email'] ?? false) !== true && !$this->wasSentToday($loan->getLastEmailReminderSentAt(), $now)) {
            $subject = sprintf('LIBRARYHUB – Mise à jour de votre pénalité (emprunt #%d)', $loanId);
            $emailSend = $this->sendEmail(
                $member,
                $subject,
                'emails/penalty_update.html.twig',
                [
                    'loan' => $loan,
                    'penalty' => $penalty,
                    'event' => $event,
                    'totalUnpaidPenalty' => $totalUnpaidPenalty,
                    'myLoansUrl' => $this->generateAbsoluteUrl('member_loans'),
                ],
                ['penalty_id' => $penaltyId, 'loan_id' => $loanId, 'event' => $event]
            );
            $result['email_sent'] = $emailSend['sent'];
            $result['email_error'] = $emailSend['error'];
            $result['should_update_email_sent_at'] = $emailSend['sent'];
        }

        $shouldSendSms = $totalUnpaidPenalty >= $this->smsPenaltyThreshold;
        if (($options['skip_sms'] ?? false) !== true && $shouldSendSms && !$this->wasSentToday($loan->getLastSmsReminderSentAt(), $now)) {
            $smsBody = sprintf(
                'Votre pénalité (emprunt #%d) a été mise à jour. Montant impayé : %s TND.',
                $loanId,
                number_format($totalUnpaidPenalty, 2, ',', ' ')
            );
            $smsSend = $this->sendSms($member, $smsBody, ['penalty_id' => $penaltyId, 'loan_id' => $loanId, 'event' => $event, 'total_unpaid_penalty' => $totalUnpaidPenalty]);
            $result['sms_sent'] = $smsSend['sent'];
            $result['sms_error'] = $smsSend['error'];
            $result['should_update_sms_sent_at'] = $smsSend['sent'];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{sent: bool, error: string|null}
     */
    private function sendEmail(User $member, string $subject, string $template, array $templateContext, array $context = []): array
    {
        $email = trim((string) $member->getEmail());
        if (!$this->isValidEmail($email)) {
            $this->logger->error('Brevo email skipped: invalid email.', $context + ['email' => $email, 'member_id' => $member->getId()]);

            return ['sent' => false, 'error' => 'Email invalide.'];
        }

        if (trim($this->brevoApiKey) === '') {
            $this->logger->error('Brevo email skipped: missing BREVO_API_KEY.', $context + ['member_id' => $member->getId()]);

            return ['sent' => false, 'error' => 'Brevo non configuré (clé API manquante).'];
        }

        $senderEmail = trim($this->brevoSenderEmail);
        if (!$this->isValidEmail($senderEmail)) {
            $this->logger->error('Brevo email skipped: invalid sender email.', $context + ['sender_email' => $senderEmail]);

            return ['sent' => false, 'error' => 'Expéditeur Brevo invalide.'];
        }

        $htmlContent = $this->twig->render($template, $templateContext);
        $textContent = trim((string) preg_replace('/\\s+/', ' ', strip_tags($htmlContent)));
        if ($textContent === '') {
            $textContent = 'Veuillez consulter votre espace LIBRARYHUB.';
        }

        try {
            $response = $this->brevo->transactionalEmails->sendTransacEmail(new SendTransacEmailRequest([
                'subject' => $subject,
                'sender' => new SendTransacEmailRequestSender([
                    'name' => $this->brevoSenderName !== '' ? $this->brevoSenderName : 'LIBRARYHUB',
                    'email' => $senderEmail,
                ]),
                'to' => [
                    new SendTransacEmailRequestToItem([
                        'email' => $email,
                        'name' => trim(sprintf('%s %s', (string) ($member->getFirstName() ?? ''), (string) ($member->getLastName() ?? ''))),
                    ]),
                ],
                'htmlContent' => $htmlContent,
                'textContent' => $textContent,
            ]));

            $this->logger->info('Brevo email sent.', $context + [
                'member_id' => $member->getId(),
                'to' => $email,
                'subject' => $subject,
                'message_id' => $response->messageId ?? null,
            ]);

            return ['sent' => true, 'error' => null];
        } catch (BrevoApiException $e) {
            $this->logger->error('Brevo API error.', $context + [
                'member_id' => $member->getId(),
                'to' => $email,
                'subject' => $subject,
                'status_code' => $e->getCode(),
                'error_message' => $e->getMessage(),
                'raw_body' => $e->getBody(),
            ]);

            return ['sent' => false, 'error' => sprintf('Erreur Brevo (%d).', (int) $e->getCode())];
        } catch (BrevoException $e) {
            $this->logger->error('Brevo SDK error.', $context + [
                'member_id' => $member->getId(),
                'to' => $email,
                'subject' => $subject,
                'error_message' => $e->getMessage(),
            ]);

            return ['sent' => false, 'error' => 'Erreur SDK Brevo.'];
        } catch (\Throwable $e) {
            $this->logger->error('Brevo unexpected error.', $context + [
                'member_id' => $member->getId(),
                'to' => $email,
                'subject' => $subject,
                'exception' => $e::class,
                'error_message' => $e->getMessage(),
            ]);

            return ['sent' => false, 'error' => 'Erreur inattendue (email).'];
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array{sent: bool, error: string|null}
     */
    private function sendSms(User $member, string $body, array $context = []): array
    {
        $phone = $this->normalizeTunisianPhone($member->getPhone());
        if ($phone === null) {
            $this->logger->error('Twilio SMS skipped: invalid phone.', $context + ['member_id' => $member->getId(), 'raw_phone' => $member->getPhone()]);

            return ['sent' => false, 'error' => 'Téléphone invalide.'];
        }

        if (trim($this->twilioSid) === '' || trim($this->twilioAuthToken) === '' || trim($this->twilioFromPhoneNumber) === '') {
            $this->logger->error('Twilio SMS skipped: missing configuration.', $context + [
                'member_id' => $member->getId(),
                'twilio_sid_set' => trim($this->twilioSid) !== '',
                'twilio_auth_token_set' => trim($this->twilioAuthToken) !== '',
                'twilio_from_set' => trim($this->twilioFromPhoneNumber) !== '',
            ]);

            return ['sent' => false, 'error' => 'Twilio non configuré.' ];
        }

        try {
            $message = $this->twilio->messages->create($phone, [
                'from' => $this->twilioFromPhoneNumber,
                'body' => $body,
            ]);

            $this->logger->info('Twilio SMS sent.', $context + [
                'member_id' => $member->getId(),
                'to' => $phone,
                'sid' => $message->sid ?? null,
            ]);

            return ['sent' => true, 'error' => null];
        } catch (TwilioException $e) {
            $this->logger->error('Twilio error.', $context + [
                'member_id' => $member->getId(),
                'to' => $phone,
                'error_message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return ['sent' => false, 'error' => 'Erreur Twilio.' ];
        } catch (\Throwable $e) {
            $this->logger->error('Twilio unexpected error.', $context + [
                'member_id' => $member->getId(),
                'to' => $phone,
                'exception' => $e::class,
                'error_message' => $e->getMessage(),
            ]);

            return ['sent' => false, 'error' => 'Erreur inattendue (SMS).' ];
        }
    }

    private function generateAbsoluteUrl(string $route, array $params = []): string
    {
        try {
            return $this->router->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Throwable) {
            $path = $this->router->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_PATH);

            return rtrim($this->publicBaseUrl, '/') . $path;
        }
    }

    private function wasSentToday(?\DateTimeInterface $lastSentAt, \DateTimeImmutable $now): bool
    {
        if (!$lastSentAt instanceof \DateTimeInterface) {
            return false;
        }

        $startOfDay = $now->setTime(0, 0, 0);
        $last = \DateTimeImmutable::createFromInterface($lastSentAt);

        return $last >= $startOfDay;
    }

    private function isValidEmail(string $email): bool
    {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
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
}
