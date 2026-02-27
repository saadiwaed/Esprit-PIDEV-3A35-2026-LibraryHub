<?php

namespace App\Service;

use App\Repository\ClubRepository;
use App\Entity\ClubEmbedding;
use Doctrine\ORM\EntityManagerInterface; // ✅ AJOUTE CETTE LIGNE
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class ClubSimilarityService
{
    private $huggingfaceToken;
    private $entityManager;
    private $httpClient;
    private $logger;
    private $apiKey;
    private $clubRepo;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        ClubRepository $clubRepo,
        EntityManagerInterface $entityManager,
        string $huggingfaceToken
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->clubRepo = $clubRepo;
        $this->entityManager = $entityManager;
        $this->huggingfaceToken = $huggingfaceToken;
    }

    /**
     * Convertit un texte en vecteur (embedding) via OpenAI
     */
    // Remplacez la méthode getEmbedding() par celle-ci :

private function getEmbedding(string $text): array
{
    try {
        // ✅ URL correcte 2025 (confirmée par HuggingFace team)
        $response = $this->httpClient->request('POST', 'https://router.huggingface.co/hf-inference/models/sentence-transformers/all-MiniLM-L6-v2/pipeline/feature-extraction', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->huggingfaceToken,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'inputs' => $text,
                'options' => ['wait_for_model' => true]
            ],
            'timeout' => 30
        ]);

        $data = $response->toArray();

        // Cas 1 : [[0.1, 0.2, ...]] (batch)
        if (isset($data[0]) && is_array($data[0])) {
            return $data[0];
        }

        // Cas 2 : [0.1, 0.2, ...] (single)
        if (!empty($data) && is_float($data[0])) {
            return $data;
        }

        $this->logger->error('Embedding format inattendu', ['response' => $data]);
        return [];

    } catch (\Exception $e) {
        $this->logger->error('HuggingFace API Error: ' . $e->getMessage());
        return [];
    }
}

/**
 * Calcule la similarité cosinus entre deux vecteurs
 */
private function cosineSimilarity(array $vecA, array $vecB): float
{
    if (empty($vecA) || empty($vecB)) {
        return 0;
    }

    $dotProduct = 0;
    $normA = 0;
    $normB = 0;

    for ($i = 0; $i < count($vecA); $i++) {
        $dotProduct += $vecA[$i] * $vecB[$i];
        $normA += $vecA[$i] * $vecA[$i];
        $normB += $vecB[$i] * $vecB[$i];
    }

    if ($normA == 0 || $normB == 0) {
        return 0;
    }

    return $dotProduct / (sqrt($normA) * sqrt($normB));
}

/**
 * Prépare le texte d'un club pour l'embedding
 */
private function prepareClubText($club): string
{
    return sprintf(
        "Club: %s\nCatégorie: %s\nDescription: %s\nMembres: %d\nÉvénements: %d",
        $club->getTitle(),
        $club->getCategory(),
        $club->getDescription(),
        $club->getMembers()->count(),
        $club->getEventCount()
    );
}

public function getClubEmbedding(int $clubId, bool $force = false): ?array
{
    $club = $this->clubRepo->find($clubId);
    if (!$club) {
        return null;
    }

    // Vérifier si l'embedding existe déjà
    $embeddingRepo = $this->entityManager->getRepository(ClubEmbedding::class);
    $existing = $embeddingRepo->findOneBy(['club' => $club]);

    if ($existing && !$force) {
        return $existing->getEmbedding();
    }

    // Générer le texte du club
    $text = $this->prepareClubText($club);

    // Obtenir l'embedding via HuggingFace
    $embedding = $this->getEmbedding($text);

    if (empty($embedding)) {
        return null;
    }

    // Sauvegarder ou mettre à jour
    if ($existing) {
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
 * Version améliorée de findSimilarClubs avec cache
 */
public function findSimilarClubs(int $clubId, int $limit = 5): array
{
    // 1. Récupérer l'embedding du club cible (depuis cache ou API)
    $targetEmbedding = $this->getClubEmbedding($clubId);

    if (empty($targetEmbedding)) {
        $this->logger->error("Impossible de générer l'embedding pour le club $clubId");
        return [];
    }

    // 2. Récupérer tous les embeddings des autres clubs
    $embeddingRepo = $this->entityManager->getRepository(ClubEmbedding::class);
    $allEmbeddings = $embeddingRepo->findAll();

    $similarities = [];

    foreach ($allEmbeddings as $embeddingEntity) {
        $otherClub = $embeddingEntity->getClub();

        if ($otherClub->getId() === $clubId) {
            continue;
        }

        $otherEmbedding = $embeddingEntity->getEmbedding();

        if (!empty($otherEmbedding)) {
            $similarity = $this->cosineSimilarity($targetEmbedding, $otherEmbedding);

            $similarities[] = [
                'club' => $otherClub,
                'score' => $similarity,
                'percentage' => round($similarity * 100, 2)
            ];
        }
    }

    // 3. Trier par similarité décroissante
    usort($similarities, fn($a, $b) => $b['score'] <=> $a['score']);

    return array_slice($similarities, 0, $limit);
}
}