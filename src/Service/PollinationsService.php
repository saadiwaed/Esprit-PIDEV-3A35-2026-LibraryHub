<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\JournalLectureRepository;
use App\Repository\DefiPersonelRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface; // ✅ CHANGER CET IMPORT !
use Psr\Cache\CacheItemInterface;

class PollinationsService
{
    private $httpClient;
    private $journalRepository;
    private $defiRepository;
    private $cache;

    public function __construct(
        HttpClientInterface $httpClient,
        JournalLectureRepository $journalRepository,
        DefiPersonelRepository $defiRepository,
        CacheInterface $cache // ✅ Ce type accepte TraceableAdapter
    ) {
        $this->httpClient = $httpClient;
        $this->journalRepository = $journalRepository;
        $this->defiRepository = $defiRepository;
        $this->cache = $cache;
    }

    /**
     * Analyse les habitudes de lecture d'un utilisateur
     */
    public function analyserHabitudesLecture(User $user): array
    {
        $lectures = $this->journalRepository->findBy(['user' => $user], ['date_lecture' => 'DESC'], 20);
        $defis = $this->defiRepository->findBy(['user_id' => $user->getId()], ['date_fin' => 'DESC'], 10);
        
        if (empty($lectures)) {
            return [
                'succes' => false,
                'contenu' => 'Commence par ajouter des lectures pour que je puisse t\'analyser !',
                'message' => 'Pas assez de données'
            ];
        }
        
        // Calculer des statistiques simples
        $totalMinutes = 0;
        $totalPages = 0;
        $totalConcentration = 0;
        $heuresLecture = [];
        
        foreach ($lectures as $lecture) {
            $totalMinutes += $lecture->getDureeMinutes();
            $totalPages += $lecture->getPageLues();
            $totalConcentration += $lecture->getConcentration();
            $heuresLecture[] = $lecture->getDateLecture()->format('H');
        }
        
        $moyennePagesMinute = $totalMinutes > 0 ? round($totalPages / ($totalMinutes / 60), 1) : 0;
        $moyenneConcentration = count($lectures) > 0 ? round($totalConcentration / count($lectures), 1) : 0;
        
        // Déterminer le moment préféré de lecture
        $frequenceHeures = array_count_values($heuresLecture);
        arsort($frequenceHeures);
        $heurePreferee = key($frequenceHeures) ?? 'inconnue';
        $momentPrefere = $this->getMomentJour($heurePreferee);
        
        // Compter les défis terminés
        $defisTermines = 0;
        foreach ($defis as $defi) {
            if ($defi->getStatut() === 'Terminé') {
                $defisTermines++;
            }
        }
        
        // Construire le prompt
        $prompt = urlencode(
            "Analyse les habitudes de lecture de {$user->getFirstName()} et donne 5 conseils personnalisés.\n\n" .
            "Statistiques:\n" .
            "- Total lectures: " . count($lectures) . "\n" .
            "- Pages lues: $totalPages\n" .
            "- Vitesse moyenne: {$moyennePagesMinute} pages/heure\n" .
            "- Concentration moyenne: {$moyenneConcentration}/10\n" .
            "- Moment préféré: $momentPrefere\n" .
            "- Défis complétés: $defisTermines\n\n" .
            "Donne des conseils pratiques, motivants et personnalisés. Format: 5 points avec emojis. Réponds en français."
        );
        
        // Clé de cache (6 heures)
        $cacheKey = 'pollinations_analyse_' . $user->getId() . '_' . date('Y-m-d-H');
        
        return $this->cache->get($cacheKey, function() use ($prompt) {
            return $this->appelerIA($prompt);
        });
    }

    /**
     * Génère des conseils personnalisés pour un défi
     */
    public function genererConseilsDefi(User $user, $defi): array
    {
        $lecturesLiees = $this->journalRepository->findBy(['defi' => $defi]);
        $progression = count($lecturesLiees);
        $reste = $defi->getObjectif() - $progression;
        
        $prompt = urlencode(
            "En tant que coach de lecture personnel, donne des conseils à un lecteur qui a un défi :\n\n" .
            "Titre: {$defi->getTitre()}\n" .
            "Description: {$defi->getDescription()}\n" .
            "Objectif: {$defi->getObjectif()} {$defi->getUnite()}\n" .
            "Progression: {$progression}/{$defi->getObjectif()}\n" .
            "Il reste: $reste {$defi->getUnite()}\n" .
            "Date fin: " . $defi->getDateFin()->format('d/m/Y') . "\n\n" .
            "Donne 3 conseils courts et motivants (2-3 phrases max). Réponds en français."
        );
        
        return $this->appelerIA($prompt);
    }

    /**
     * Suggère des défis personnalisés
     */
    public function suggererDefis(User $user): array
    {
        $lectures = $this->journalRepository->findBy(['user' => $user], ['date_lecture' => 'DESC'], 30);
        
        if (count($lectures) < 3) {
            return [
                'succes' => false,
                'contenu' => 'Ajoute au moins 3 lectures pour obtenir des suggestions personnalisées !',
                'message' => 'Pas assez de données'
            ];
        }
        
        $prompt = urlencode(
            "Basé sur l'historique de lecture d'un utilisateur, suggère 3 défis de lecture personnalisés. " .
            "Format de réponse souhaité: une liste avec titre, description courte et objectif réalisable. " .
            "Réponds en français."
        );
        
        return $this->appelerIA($prompt);
    }

    /**
     * Appelle l'API Pollinations
     */
    private function appelerIA(string $prompt, int $retryCount = 0): array
    {
        try {
            $response = $this->httpClient->request('GET', "https://text.pollinations.ai/{$prompt}", [
                'timeout' => 30,
            ]);
            
            $contenu = $response->getContent();
            
            return [
                'succes' => true,
                'contenu' => $contenu,
                'message' => 'Analyse générée avec succès'
            ];
            
        } catch (\Exception $e) {
            if ($retryCount < 2) {
                sleep(2);
                return $this->appelerIA($prompt, $retryCount + 1);
            }
            
            return [
                'succes' => false,
                'contenu' => 'Désolé, le service n\'est pas disponible pour le moment.',
                'message' => 'Erreur: ' . $e->getMessage()
            ];
        }
    }

    private function getMomentJour(string $heure): string
    {
        $h = (int)$heure;
        if ($h >= 5 && $h < 12) return 'matin';
        if ($h >= 12 && $h < 18) return 'après-midi';
        if ($h >= 18 && $h < 22) return 'soir';
        return 'nuit';
    }
}