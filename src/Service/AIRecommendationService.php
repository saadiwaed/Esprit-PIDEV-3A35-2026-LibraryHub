<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ClubRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class AIRecommendationService
{
    private string $aiServiceUrl;

    public function __construct(
        private HttpClientInterface $httpClient,
        private ClubRepository $clubRepository,
        private LoggerInterface $logger,
        string $aiServiceUrl = 'http://localhost:8000'
    ) {
        $this->aiServiceUrl = $aiServiceUrl;
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->aiServiceUrl . '/health', [
                'timeout' => 2
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            $this->logger->warning('Service IA indisponible: ' . $e->getMessage());
            return false;
        }
    }

    public function getClubRecommendations(User $user, int $topN = 5): array
    {
        // Récupérer tous les clubs actifs
        $allClubs = $this->clubRepository->findAll();
        $allClubIds = array_map(fn($c) => $c->getId(), $allClubs);

        // Récupérer les IDs des clubs déjà rejoints
        $joinedIds = array_map(
            fn($c) => $c->getId(),
            $user->getClubs()->toArray()
        );

        // Si le service IA n'est pas disponible, fallback
        if (!$this->isAvailable()) {
            $this->logger->info('Utilisation du fallback (popularité)');
            return $this->getPopularClubsFallback($user, $topN);
        }

        try {
            // Appeler le service IA
            $response = $this->httpClient->request('POST', $this->aiServiceUrl . '/recommend/clubs', [
                'json' => [
                    'user_id' => $user->getId(),
                    'all_club_ids' => $allClubIds,
                    'exclude_ids' => $joinedIds,
                    'top_n' => $topN
                ],
                'timeout' => 3
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Erreur service IA: ' . $response->getContent(false));
            }

            $data = $response->toArray();
            $recommendedIds = $data['recommended_clubs'];

            // Récupérer les entités Club dans l'ordre recommandé
            return $this->getClubsInOrder($recommendedIds);

        } catch (\Exception $e) {
            $this->logger->error('Erreur appel service IA: ' . $e->getMessage());
            return $this->getPopularClubsFallback($user, $topN);
        }
    }

    private function getPopularClubsFallback(User $user, int $limit): array
    {
        $allClubs = $this->clubRepository->findAll();
        $joinedIds = array_map(fn($c) => $c->getId(), $user->getClubs()->toArray());
        
        // Filtrer les clubs déjà rejoints
        $candidates = array_filter($allClubs, fn($c) => !in_array($c->getId(), $joinedIds));
        
        // Trier par nombre de membres (popularité)
        usort($candidates, fn($a, $b) => $b->getMembers()->count() <=> $a->getMembers()->count());
        
        return array_slice($candidates, 0, $limit);
    }

    private function getClubsInOrder(array $ids): array
    {
        $clubs = $this->clubRepository->findBy(['id' => $ids]);
        
        // Réordonner selon l'ordre des IDs
        $ordered = [];
        $clubMap = [];
        foreach ($clubs as $club) {
            $clubMap[$club->getId()] = $club;
        }
        
        foreach ($ids as $id) {
            if (isset($clubMap[$id])) {
                $ordered[] = $clubMap[$id];
            }
        }
        
        return $ordered;
    }
}