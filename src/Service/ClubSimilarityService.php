<?php

namespace App\Service;

use App\Entity\Club;
use App\Entity\ClubEmbedding;
use App\Repository\ClubRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ClubSimilarityService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ClubRepository $clubRepo,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $huggingfaceToken
    ) {
    }

    /**
     * @return list<float>
     */
    private function getEmbedding(string $text): array
    {
        try {
            $response = $this->httpClient->request('POST', 'https://router.huggingface.co/hf-inference/models/sentence-transformers/all-MiniLM-L6-v2/pipeline/feature-extraction', [
                'headers' => [
                    'Authorization' => 'Bearer '.$this->huggingfaceToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'inputs' => $text,
                    'options' => ['wait_for_model' => true],
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray();

            if (isset($data[0]) && is_array($data[0])) {
                /** @var list<mixed> $first */
                $first = $data[0];
                $vector = $this->normalizeVector($first);

                if ($vector !== []) {
                    return $vector;
                }
            }

            /** @var list<mixed> $flat */
            $flat = array_values($data);
            $vector = $this->normalizeVector($flat);

            if ($vector !== []) {
                return $vector;
            }

            $this->logger->error('Embedding format inattendu', ['response' => $data]);

            return [];
        } catch (\Throwable $e) {
            $this->logger->error('HuggingFace API Error: '.$e->getMessage());

            return [];
        }
    }

    /**
     * @param list<mixed> $values
     * @return list<float>
     */
    private function normalizeVector(array $values): array
    {
        $vector = [];
        foreach ($values as $value) {
            if (!is_int($value) && !is_float($value)) {
                return [];
            }

            $vector[] = (float) $value;
        }

        return $vector;
    }

    /**
     * @param list<float> $vecA
     * @param list<float> $vecB
     */
    private function cosineSimilarity(array $vecA, array $vecB): float
    {
        if ($vecA === [] || $vecB === []) {
            return 0.0;
        }

        $size = min(count($vecA), count($vecB));

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < $size; $i++) {
            $dotProduct += $vecA[$i] * $vecB[$i];
            $normA += $vecA[$i] * $vecA[$i];
            $normB += $vecB[$i] * $vecB[$i];
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    private function prepareClubText(Club $club): string
    {
        return sprintf(
            "Club: %s\nCategorie: %s\nDescription: %s\nMembres: %d\nEvenements: %d",
            $club->getTitle(),
            $club->getCategory(),
            $club->getDescription(),
            $club->getMembers()->count(),
            $club->getEventCount()
        );
    }

    /**
     * @return list<float>|null
     */
    public function getClubEmbedding(int $clubId, bool $force = false): ?array
    {
        $club = $this->clubRepo->find($clubId);
        if (!$club instanceof Club) {
            return null;
        }

        $embeddingRepo = $this->entityManager->getRepository(ClubEmbedding::class);
        $existing = $embeddingRepo->findOneBy(['club' => $club]);
        if ($existing instanceof ClubEmbedding && !$force) {
            return $this->toFloatList($existing->getEmbedding());
        }

        $embedding = $this->getEmbedding($this->prepareClubText($club));
        if ($embedding === []) {
            return null;
        }

        if ($existing instanceof ClubEmbedding) {
            $existing->setEmbedding($embedding);
            $existing->setGeneratedAt(new \DateTimeImmutable());
        } else {
            $clubEmbedding = new ClubEmbedding();
            $clubEmbedding->setClub($club);
            $clubEmbedding->setEmbedding($embedding);
            $this->entityManager->persist($clubEmbedding);
        }

        $this->entityManager->flush();

        return $embedding;
    }

    /**
     * @return list<array{club: Club, score: float, percentage: float}>
     */
    public function findSimilarClubs(int $clubId, int $limit = 5): array
    {
        $targetEmbedding = $this->getClubEmbedding($clubId);
        if ($targetEmbedding === null || $targetEmbedding === []) {
            $this->logger->error("Impossible de generer l'embedding pour le club $clubId");

            return [];
        }

        $embeddingRepo = $this->entityManager->getRepository(ClubEmbedding::class);
        /** @var list<ClubEmbedding> $allEmbeddings */
        $allEmbeddings = $embeddingRepo->findAll();

        $similarities = [];

        foreach ($allEmbeddings as $embeddingEntity) {
            $otherClub = $embeddingEntity->getClub();
            if (!$otherClub instanceof Club || $otherClub->getId() === $clubId) {
                continue;
            }

            $otherEmbedding = $this->toFloatList($embeddingEntity->getEmbedding());
            if ($otherEmbedding === []) {
                continue;
            }

            $similarity = $this->cosineSimilarity($targetEmbedding, $otherEmbedding);
            $similarities[] = [
                'club' => $otherClub,
                'score' => $similarity,
                'percentage' => round($similarity * 100, 2),
            ];
        }

        usort(
            $similarities,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score']
        );

        return array_slice($similarities, 0, max(1, $limit));
    }

    /**
     * @param array<int, mixed> $values
     * @return list<float>
     */
    private function toFloatList(array $values): array
    {
        $result = [];
        foreach ($values as $value) {
            if (!is_int($value) && !is_float($value)) {
                continue;
            }

            $result[] = (float) $value;
        }

        return $result;
    }
}
