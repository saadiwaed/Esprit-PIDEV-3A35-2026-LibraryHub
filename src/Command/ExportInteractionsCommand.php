<?php

namespace App\Command;

use App\Entity\Club;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class ExportInteractionsCommand extends Command
{
    protected static $defaultName = 'app:export-interactions';

    public function __construct(
        private EntityManagerInterface $em,
        private string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Exporte les interactions utilisateur-club pour l\'entraînement du modèle IA');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fs = new Filesystem();

        $io->title('📤 Export des interactions pour l\'IA');

        // Créer le dossier data dans ai-service
        $dataDir = $this->projectDir . '/ai-service/data';
        $fs->mkdir($dataDir);
        $csvPath = $dataDir . '/interactions.csv';

        $io->section('Collecte des données');

        // Récupérer tous les utilisateurs
        $users = $this->em->getRepository(User::class)->findAll();
        $io->text(sprintf('📊 Utilisateurs trouvés: %d', count($users)));

        // Récupérer tous les clubs
        $clubs = $this->em->getRepository(Club::class)->findAll();
        $io->text(sprintf('📚 Clubs trouvés: %d', count($clubs)));

        $io->section('Génération du fichier CSV');

        $handle = fopen($csvPath, 'w');
        fputcsv($handle, ['user_id', 'club_id', 'rating', 'interaction_type']);

        $totalInteractions = 0;

        foreach ($users as $user) {
            $userId = $user->getId();

            // 1. Clubs fondés (rating 5) - via la relation founder
            $foundedClubs = $this->em->getRepository(Club::class)->findBy(['founder' => $user]);
            foreach ($foundedClubs as $club) {
                fputcsv($handle, [$userId, $club->getId(), 5, 'founded']);
                $totalInteractions++;
                $io->text(sprintf('   👑 User %d a fondé Club %d (rating 5)', $userId, $club->getId()));
            }

            // 2. Clubs rejoints (rating 4)
            foreach ($user->getClubs() as $club) {
                // Éviter les doublons avec les clubs fondés
                $isFounded = false;
                foreach ($foundedClubs as $foundedClub) {
                    if ($foundedClub->getId() === $club->getId()) {
                        $isFounded = true;
                        break;
                    }
                }
                
                if (!$isFounded) {
                    fputcsv($handle, [$userId, $club->getId(), 4, 'joined']);
                    $totalInteractions++;
                    $io->text(sprintf('   👥 User %d a rejoint Club %d (rating 4)', $userId, $club->getId()));
                }
            }
        }

        fclose($handle);

        $io->success(sprintf(
            '✅ Export terminé: %d interactions sauvegardées dans %s',
            $totalInteractions,
            $csvPath
        ));

        // Afficher un aperçu
        if ($totalInteractions > 0) {
            $io->section('Aperçu du fichier généré');
            $io->text('Contenu du fichier:');
            
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                // Windows
                system(sprintf('type %s', $csvPath));
            } else {
                // Linux/Mac
                system(sprintf('cat %s', $csvPath));
            }
        }

        return Command::SUCCESS;
    }
}