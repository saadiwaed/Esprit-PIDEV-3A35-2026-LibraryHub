<?php

namespace App\Service;

use OpenAI;
use App\Entity\User;
use App\Repository\JournalLectureRepository;
use App\Repository\DefiPersonelRepository;
use Symfony\Contracts\Cache\CacheInterface;

class IAService
{
    private $client;
    private $journalRepository;
    private $defiRepository;
    private $cache;

    


    public function __construct(
        JournalLectureRepository $journalRepository,
        DefiPersonelRepository $defiRepository,
        CacheInterface $cache,
        string $openAiApiKey

    ) {
        if (trim($openAiApiKey) === '') {
            throw new \RuntimeException('La variable d\'environnement OPENAI_API_KEY_waed est vide ou absente.');
        }

        $this->client = OpenAI::client($openAiApiKey);
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
    
    // Préparer les statistiques
    $stats = [
        'moyenne_pages_minute' => $moyennePagesMinute,
        'moyenne_concentration' => $moyenneConcentration,
        'moment_prefere' => $momentPrefere,
        'total_lectures' => count($lectures),
        'total_pages' => $totalPages,
    ];
    
    // ✅ CRÉER UNE CLÉ DE CACHE UNIQUE (change toutes les heures)
    $cacheKey = 'analyse_habitudes_' . $user->getId() . '_' . date('Y-m-d-H');
    
    // ✅ CONSTRUIRE LE PROMPT
    $prompt = $this->construirePromptAnalyse($user, $lectures, $defis, $stats);
    
    // ✅ UTILISER LE CACHE SI DISPONIBLE
    if (isset($this->cache)) {
        $resultat = $this->cache->get($cacheKey, function() use ($prompt) {
            return $this->appelerIA($prompt);
        });

        // Evite de garder un echec IA en cache pendant 1h.
        if (!(bool) ($resultat['succes'] ?? false)) {
            $this->cache->delete($cacheKey);
        }

        return $resultat;
    }
    
    // ✅ SINON, APPEL DIRECT
    return $this->appelerIA($prompt);
}

    /**
     * Génère des conseils personnalisés pour un défi
     */
    public function genererConseilsDefi(User $user, $defi): array
    {
        $lecturesLiees = $this->journalRepository->findBy(['defi' => $defi]);
        $autresLectures = $this->journalRepository->findBy(['user' => $user], ['date_lecture' => 'DESC'], 10);
        
        $progression = count($lecturesLiees);
        $reste = $defi->getObjectif() - $progression;
        
        $prompt = "En tant que coach de lecture personnel, donne des conseils à un lecteur qui a un défi : " .
                 "\n\nTitre du défi: {$defi->getTitre()}" .
                 "\nDescription: {$defi->getDescription()}" .
                 "\nObjectif: {$defi->getObjectif()} {$defi->getUnite()}" .
                 "\nProgression actuelle: {$progression} {$defi->getUnite()}" .
                 "\nIl reste: {$reste} {$defi->getUnite()} à atteindre" .
                 "\nDate fin: " . $defi->getDateFin()->format('d/m/Y') .
                 "\n\nBasé sur son historique de " . count($autresLectures) . " lectures, " .
                 "donne 3 conseils courts et motivants (2-3 phrases maximum par conseil).";
        
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
                'message' => 'Ajoute au moins 3 lectures pour obtenir des suggestions personnalisées !',
                'suggestions' => []
            ];
        }
        
        // Analyser les genres (simulé pour l'exemple)
        $genres = ['Science-Fiction', 'Roman', 'Policier', 'Historique', 'Philosophie'];
        $genresLus = array_rand(array_flip($genres), min(3, count($genres)));
        
        $prompt = "Basé sur l'historique de lecture d'un utilisateur, suggère 3 défis de lecture personnalisés. " .
                 "Format de réponse souhaité: une liste avec titre, description courte et objectif réalisable.";
        
        return $this->appelerIA($prompt);
    }

    /**
     * Appelle l'API OpenAI
     */
    /**
 * Appelle l'API OpenAI avec gestion des erreurs de taux
 */
private function appelerIA(string $prompt, int $retryCount = 0): array
{
    $maxRetries = 3;
    
    try {
        $response = $this->client->chat()->create([
        'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu es un assistant personnel spécialisé dans la lecture et le développement personnel. ' .
                                'Tu donnes des conseils motivants, personnalisés et pratiques. ' .
                                'Réponds toujours en français de manière chaleureuse et encourageante.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 200, // ✅ RÉDUIRE LE NOMBRE DE TOKENS
        ]);
        
        $conseil = $response->choices[0]->message->content ?? '';
        
        return [
            'succes' => true,
            'contenu' => $conseil,
            'message' => 'Analyse générée avec succès'
        ];
        
    } catch (\Exception $e) {
        $errorMessage = $e->getMessage();
        
        // ✅ DÉTECTER SI C'EST UNE ERREUR DE LIMITE DE TAUX
        if (strpos($errorMessage, 'rate limit') !== false ||
            strpos($errorMessage, '429') !== false ||
            strpos($errorMessage, 'quota') !== false) {
            
            if ($retryCount < $maxRetries) {
                // Attendre de plus en plus longtemps entre les tentatives
                $waitTime = ($retryCount + 1) * 2; // 2, 4, 6 secondes
                sleep($waitTime);
                
                // Réessayer
                return $this->appelerIA($prompt, $retryCount + 1);
            }
            
            return [
                'succes' => false,
                'contenu' => "🤖 L'IA est un peu surchargée en ce moment. Réessaie dans quelques minutes !",
                'message' => 'Limite de taux dépassée. Réessaie plus tard.'
            ];
        }
        
        return [
            'succes' => false,
            'contenu' => 'Désolé, l\'IA n\'est pas disponible pour le moment.',
            'message' => 'Erreur: ' . $errorMessage
        ];
    }
}

    /**
     * Construit le prompt d'analyse
     */
    private function construirePromptAnalyse(User $user, array $lectures, array $defis, array $stats): string
    {
        return "Analyse les habitudes de lecture de {$user->getFirstName()} et donne 5 conseils personnalisés.\n\n" .
               "Statistiques:\n" .
               "- Total lectures: {$stats['total_lectures']}\n" .
               "- Pages lues: {$stats['total_pages']}\n" .
               "- Vitesse moyenne: {$stats['moyenne_pages_minute']} pages/heure\n" .
               "- Concentration moyenne: {$stats['moyenne_concentration']}/10\n" .
               "- Moment préféré: {$stats['moment_prefere']}\n" .
               "- Défs complétés: " . count(array_filter($defis, fn($d) => $d->getStatut() === 'Terminé')) . "\n\n" .
               "Donne des conseils pratiques, motivants et personnalisés. Format: 5 points avec emojis.";
    }

    /**
     * Convertit une heure en moment de la journée
     */
    private function getMomentJour(string $heure): string
    {
        $h = (int)$heure;
        if ($h >= 5 && $h < 12) return 'matin';
        if ($h >= 12 && $h < 18) return 'après-midi';
        if ($h >= 18 && $h < 22) return 'soir';
        return 'nuit';
    }

    /**
 * Version avec cache pour éviter les appels répétés
 */
private function appelerIAvecCache(string $cacheKey, string $prompt): array
{
    // Mettre en cache pendant 1 heure
    return $this->cache->get($cacheKey, function() use ($prompt) {
        return $this->appelerIA($prompt);
    });
}
}
