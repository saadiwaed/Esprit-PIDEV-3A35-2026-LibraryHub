<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleBooksService
{
    private const BASE_URL = 'https://www.googleapis.com/books/v1';

    private HttpClientInterface $client;
    private string $apiKey;

    public function __construct(string $googleBooksApiKey)
    {
        $this->client = HttpClient::create(['timeout' => 30]);
        $this->apiKey = $googleBooksApiKey;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchBooks(string $query, int $maxResults = 10): array
    {
        $url = self::BASE_URL.'/volumes';

        try {
            $response = $this->client->request('GET', $url, [
                'query' => [
                    'q' => $query,
                    'key' => $this->apiKey,
                    'maxResults' => $maxResults,
                    'langRestrict' => 'fr',
                    'orderBy' => 'relevance',
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Erreur API: '.$response->getStatusCode());
            }

            $data = $response->toArray();
            $itemsRaw = $data['items'] ?? [];
            /** @var list<mixed> $items */
            $items = is_array($itemsRaw) ? array_values($itemsRaw) : [];

            return $this->formatBooks($items);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('Erreur de connexion: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function searchByIsbn(string $isbn): ?array
    {
        $cleanIsbn = preg_replace('/[^0-9X]/', '', $isbn) ?? '';
        $results = $this->searchBooks('isbn:'.$cleanIsbn, 1);

        return $results[0] ?? null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchByAuthor(string $author, int $maxResults = 10): array
    {
        return $this->searchBooks('inauthor:'.$author, $maxResults);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchByCategory(string $category, int $maxResults = 10): array
    {
        return $this->searchBooks('subject:'.$category, $maxResults);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBookDetails(string $bookId): ?array
    {
        $url = self::BASE_URL.'/volumes/'.$bookId;

        try {
            $response = $this->client->request('GET', $url, [
                'query' => ['key' => $this->apiKey],
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            /** @var array<string, mixed> $data */
            $data = $response->toArray();

            return $this->formatBook($data);
        } catch (TransportExceptionInterface) {
            return null;
        }
    }

    /**
     * @param list<mixed> $items
     * @return list<array<string, mixed>>
     */
    private function formatBooks(array $items): array
    {
        $formatted = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $formatted[] = $this->formatBook($item);
        }

        return $formatted;
    }

    /**
     * @param array<string, mixed> $bookData
     * @return array<string, mixed>
     */
    private function formatBook(array $bookData): array
    {
        $volumeInfo = is_array($bookData['volumeInfo'] ?? null) ? $bookData['volumeInfo'] : [];
        $saleInfo = is_array($bookData['saleInfo'] ?? null) ? $bookData['saleInfo'] : [];
        $industryIdentifiers = is_array($volumeInfo['industryIdentifiers'] ?? null) ? $volumeInfo['industryIdentifiers'] : [];

        $isbn = null;
        $isbn13 = null;
        $isbn10 = null;

        foreach ($industryIdentifiers as $identifier) {
            if (!is_array($identifier)) {
                continue;
            }

            $type = is_string($identifier['type'] ?? null) ? $identifier['type'] : '';
            $value = is_string($identifier['identifier'] ?? null) ? $identifier['identifier'] : null;

            if ($type === 'ISBN_13') {
                $isbn13 = $value;
                $isbn = $isbn13;
            } elseif ($type === 'ISBN_10') {
                $isbn10 = $value;
                $isbn = $isbn10;
            }
        }

        $imageLinks = is_array($volumeInfo['imageLinks'] ?? null) ? $volumeInfo['imageLinks'] : [];
        $thumbnail = is_string($imageLinks['thumbnail'] ?? null) ? $imageLinks['thumbnail'] : null;
        $smallThumbnail = is_string($imageLinks['smallThumbnail'] ?? null) ? $imageLinks['smallThumbnail'] : null;
        $image = $thumbnail ?? $smallThumbnail;

        if ($image !== null && str_starts_with($image, 'http://')) {
            $image = 'https://'.substr($image, 7);
        }

        $listPrice = is_array($saleInfo['listPrice'] ?? null) ? $saleInfo['listPrice'] : [];

        return [
            'id' => $bookData['id'] ?? null,
            'title' => is_string($volumeInfo['title'] ?? null) ? $volumeInfo['title'] : 'Titre inconnu',
            'subtitle' => is_string($volumeInfo['subtitle'] ?? null) ? $volumeInfo['subtitle'] : '',
            'authors' => is_array($volumeInfo['authors'] ?? null) ? $volumeInfo['authors'] : ['Auteur inconnu'],
            'description' => is_string($volumeInfo['description'] ?? null) ? $volumeInfo['description'] : 'Pas de description disponible.',
            'publishedDate' => $volumeInfo['publishedDate'] ?? null,
            'publisher' => $volumeInfo['publisher'] ?? null,
            'pageCount' => is_numeric($volumeInfo['pageCount'] ?? null) ? (int) $volumeInfo['pageCount'] : 0,
            'categories' => is_array($volumeInfo['categories'] ?? null) ? $volumeInfo['categories'] : [],
            'language' => is_string($volumeInfo['language'] ?? null) ? $volumeInfo['language'] : 'fr',
            'isbn' => $isbn,
            'isbn10' => $isbn10,
            'isbn13' => $isbn13,
            'image' => $image,
            'previewLink' => $volumeInfo['previewLink'] ?? null,
            'infoLink' => $volumeInfo['infoLink'] ?? null,
            'rating' => is_numeric($volumeInfo['averageRating'] ?? null) ? (float) $volumeInfo['averageRating'] : 0.0,
            'ratingsCount' => is_numeric($volumeInfo['ratingsCount'] ?? null) ? (int) $volumeInfo['ratingsCount'] : 0,
            'price' => is_numeric($listPrice['amount'] ?? null) ? (float) $listPrice['amount'] : null,
            'currency' => is_string($listPrice['currencyCode'] ?? null) ? $listPrice['currencyCode'] : 'EUR',
            'isFree' => (string) ($saleInfo['saleability'] ?? '') === 'FREE',
            'isEbook' => ($saleInfo['isEbook'] ?? false) === true,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suggestForClub(string $clubCategory, int $limit = 5): array
    {
        $categoryMap = [
            'romance' => 'love',
            'science-fiction' => 'science fiction',
            'fantasy' => 'fantasy',
            'polar' => 'mystery',
            'historique' => 'history',
            'biographie' => 'biography',
            'thriller' => 'thriller',
            'classique' => 'classic',
            'poesie' => 'poetry',
            'theatre' => 'drama',
        ];

        $subject = $categoryMap[strtolower($clubCategory)] ?? $clubCategory;

        return $this->searchByCategory($subject, $limit);
    }

    public function testConnection(): bool
    {
        try {
            $this->searchBooks('test', 1);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
