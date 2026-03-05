<?php

namespace App\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twilio\Exceptions\TwilioException;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client as TwilioClient;

#[AsCommand(
    name: 'app:sms:loan-accepted',
    description: 'Send a one-off SMS saying a loan was accepted (use --send to actually send).',
)]
final class SendLoanAcceptedSmsCommand extends Command
{
    public function __construct(
        private readonly TwilioClient $twilio,
        private readonly LoggerInterface $logger,
        private readonly string $fromPhoneNumber,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Recipient phone number (E.164, e.g. +216XXXXXXXX).')
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Custom SMS body (optional).', '')
            ->addOption('send', null, InputOption::VALUE_NONE, 'Actually send the SMS (default is dry-run).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $toRaw = trim((string) $input->getOption('to'));
        if ($toRaw === '') {
            $io->error('Missing required option: --to');
            return Command::INVALID;
        }

        $to = $this->normalizeE164($toRaw);
        if ($to === null) {
            $io->error('Invalid phone number. Expected E.164 format like +216XXXXXXXX.');
            return Command::INVALID;
        }

        $from = trim($this->fromPhoneNumber);
        if ($from === '') {
            $io->error('TWILIO_PHONE_NUMBER is not configured.');
            return Command::FAILURE;
        }

        $custom = trim((string) $input->getOption('message'));
        $body = $custom !== ''
            ? $custom
            : "Bonjour, votre emprunt a ete accepte. – LIBRARYHUB";

        $shouldSend = (bool) $input->getOption('send');
        if (!$shouldSend) {
            $io->warning('Dry-run (no SMS sent). Re-run with --send to send for real.');
            $io->writeln(sprintf('To: %s', $to));
            $io->writeln(sprintf('From: %s', $from));
            $io->writeln(sprintf('Body: %s', $body));
            return Command::SUCCESS;
        }

        try {
            $message = $this->twilio->messages->create($to, [
                'from' => $from,
                'body' => $body,
            ]);

            $io->success(sprintf('SMS sent. Twilio SID: %s', (string) ($message->sid ?? '')));
            return Command::SUCCESS;
        } catch (RestException $e) {
            $this->logger->error('Twilio error while sending one-off SMS.', [
                'to' => $to,
                'from' => $from,
                'exception' => $e,
            ]);
            $io->error(sprintf(
                'Twilio error (code %s, HTTP %s): %s',
                (string) $e->getCode(),
                method_exists($e, 'getStatusCode') ? (string) ($e->getStatusCode() ?? '') : '',
                $e->getMessage()
            ));
            return Command::FAILURE;
        } catch (TwilioException $e) {
            $this->logger->error('Twilio error while sending one-off SMS.', [
                'to' => $to,
                'from' => $from,
                'exception' => $e,
            ]);
            $io->error(sprintf('Twilio error: %s', $e->getMessage()));
            return Command::FAILURE;
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error while sending one-off SMS.', [
                'to' => $to,
                'from' => $from,
                'exception' => $e,
            ]);
            $io->error('Unexpected error while sending SMS (see logs).');
            return Command::FAILURE;
        }
    }

    private function normalizeE164(string $raw): ?string
    {
        $value = preg_replace('/\s+/', '', trim($raw));
        $value = str_replace(['-', '(', ')', '.'], '', (string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^\+\d{8,15}$/', $value) === 1) {
            return $value;
        }

        return null;
    }
}
