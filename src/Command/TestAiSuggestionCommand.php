<?php

namespace App\Command;

use App\Repository\RenewalRequestRepository;
use App\Service\AIRenewalSuggester;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:test-ai-suggestion',
    description: 'Teste la suggestion IA pour une demande de renouvellement (argument = ID de RenewalRequest).'
)]
final class TestAiSuggestionCommand extends Command
{
    public function __construct(
        private readonly RenewalRequestRepository $renewalRequestRepository,
        private readonly AIRenewalSuggester $aiRenewalSuggester,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('loanRequestId', InputArgument::REQUIRED, 'ID de la demande de renouvellement (RenewalRequest).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $idRaw = $input->getArgument('loanRequestId');
        $id = is_numeric($idRaw) ? (int) $idRaw : 0;
        if ($id <= 0) {
            $output->writeln('<error>ID invalide.</error>');
            return Command::INVALID;
        }

        $renewalRequest = $this->renewalRequestRepository->find($id);
        if ($renewalRequest === null) {
            $output->writeln(sprintf('<error>Aucune demande de renouvellement trouvée pour l’ID %d.</error>', $id));
            return Command::FAILURE;
        }

        $suggestion = $this->aiRenewalSuggester->getSuggestion($renewalRequest);

        $output->writeln(json_encode($suggestion, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }
}

