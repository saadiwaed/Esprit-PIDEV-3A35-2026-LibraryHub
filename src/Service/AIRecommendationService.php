<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\User;
use App\Repository\ClubRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
            $response = $this->httpClient->request('GET', $this->aiServiceUrl.'/health', [
                'timeout' => 2,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            $this->logger->warning('Service IA indisponible: '.$e->getMessage());

            return false;
        }
    }

    /**
     * @return list<Club>
     */
    public function getClubRecommendations(User $user, int $topN = 5): array
    {
        /** @var list<Club> $allClubs */
        $allClubs = $this->clubRepository->findAll();
        $allClubIds = $this->extractPersistedClubIds($allClubs);
        $joinedIds = $this->extractPersistedClubIds($user->getClubs()->toArray());

        if (!$this->isAvailable()) {
            $this->logger->info('Utilisation du fallback (popularite)');

            return $this->getPopularClubsFallback($user, $topN);
        }

        try {
            $response = $this->httpClient->request('POST', $this->aiServiceUrl.'/recommend/clubs', [
                'json' => [
                    'user_id' => $user->getId(),
                    'all_club_ids' => $allClubIds,
                    'exclude_ids' => $joinedIds,
                    'top_n' => $topN,
                ],
                'timeout' => 3,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Erreur service IA: '.$response->getContent(false));
            }

            /** @var array<string, mixed> $data */
            $data = $response->toArray();
            $recommendedIds = $this->normalizeIdList($data['recommended_clubs'] ?? []);

            return $this->getClubsInOrder($recommendedIds);
        } catch (\Exception $e) {
            $this->logger->error('Erreur appel service IA: '.$e->getMessage());

            return $this->getPopularClubsFallback($user, $topN);
        }
    }

    /**
     * @return list<Club>
     */
    private function getPopularClubsFallback(User $user, int $limit): array
    {
        /** @var list<Club> $allClubs */
        $allClubs = $this->clubRepository->findAll();
        $joinedIds = $this->extractPersistedClubIds($user->getClubs()->toArray());

        $candidates = [];
        foreach ($allClubs as $club) {
            $clubId = $club->getId();
            if (!is_int($clubId) || in_array($clubId, $joinedIds, true)) {
                continue;
            }

            $candidates[] = $club;
        }

        usort(
            $candidates,
            static fn (Club $a, Club $b): int => $b->getMembers()->count() <=> $a->getMembers()->count()
        );

        /** @var list<Club> $sliced */
        $sliced = array_slice($candidates, 0, max(1, $limit));

        return $sliced;
    }

    /**
     * @param list<int> $ids
     * @return list<Club>
     */
    private function getClubsInOrder(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var list<Club> $clubs */
        $clubs = $this->clubRepository->findBy(['id' => $ids]);
        /** @var array<int, Club> $clubMap */
        $clubMap = [];
        foreach ($clubs as $club) {
            $clubId = $club->getId();
            if (is_int($clubId)) {
                $clubMap[$clubId] = $club;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($clubMap[$id])) {
                $ordered[] = $clubMap[$id];
            }
        }

        return $ordered;
    }

    /**
     * @param iterable<mixed> $clubs
     * @return list<int>
     */
    private function extractPersistedClubIds(iterable $clubs): array
    {
        $ids = [];
        foreach ($clubs as $club) {
            if (!$club instanceof Club) {
                continue;
            }

            $clubId = $club->getId();
            if (is_int($clubId)) {
                $ids[] = $clubId;
            }
        }

        return $ids;
    }

    /**
     * @param mixed $value
     * @return list<int>
     */
    private function normalizeIdList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $rawId) {
            if (is_int($rawId)) {
                $ids[] = $rawId;
            }
        }

        return $ids;
    }
}
