<?php

namespace App\Command;

use App\Entity\Club;
use App\Service\ClubSimilarityService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateClubEmbeddingsCommand extends Command
{
    protected static $defaultName = 'app:generate-embeddings';
    
    private $entityManager;
    private $similarityService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ClubSimilarityService $similarityService
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->similarityService = $similarityService;
    }

    protected function configure(): void
    {
        $this->setDescription('Génère les embeddings pour tous les clubs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
{
    $io = new SymfonyStyle($input, $output);
    
    $io->title('🚀 Génération des embeddings pour tous les clubs');
    
    $clubs = $this->entityManager->getRepository(Club::class)->findAll();
    $io->progressStart(count($clubs));
    
    $success = 0;
    $errors = 0;
    
    $count = 0;
foreach ($clubs as $club) {
    try {
        $this->similarityService->getClubEmbedding($club->getId(), true);
        $io->progressAdvance();
        $success++;
        $count++;
        
        // ✅ TOUTES LES 10 REQUÊTES, PAUSE DE 5 SECONDES
        if ($count % 10 == 0) {
            $io->writeln("\n⏸️ Pause de 5 secondes pour respecter les limites...");
            sleep(5);
        } else {
            sleep(1);
        }
        
    } catch (\Exception $e) {
        $io->writeln("\n❌ Erreur: " . $e->getMessage());
        $errors++;
    }
}
    
    $io->progressFinish();
    
    $io->success([
        "Génération terminée !",
        "✅ Succès: $success clubs",
        "❌ Erreurs: $errors clubs"
    ]);
    
    return Command::SUCCESS;
}
}