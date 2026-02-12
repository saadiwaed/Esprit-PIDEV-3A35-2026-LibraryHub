<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GoogleBooksService
{
    private HttpClientInterface $client;
    private string $apiKey;
    
    // URL de base de l'API
    private const BASE_URL = 'https://www.googleapis.com/books/v1';
    
    public function __construct(string $googleBooksApiKey)
    {
        $this->client = HttpClient::create([
            'timeout' => 30, // Timeout de 30 secondes
        ]);
        $this->apiKey = $googleBooksApiKey;
    }
    
    /**
     * Recherche de livres
     */
    public function searchBooks(string $query, int $maxResults = 10): array
    {
        $url = self::BASE_URL . '/volumes';
        
        try {
            $response = $this->client->request('GET', $url, [
                'query' => [
                    'q' => $query,
                    'key' => $this->apiKey,
                    'maxResults' => $maxResults,
                    'langRestrict' => 'fr', // Livres en français
                    'orderBy' => 'relevance'
                ]
            ]);
            
            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Erreur API: ' . $response->getStatusCode());
            }
            
            $data = $response->toArray();
            
            // Formater les résultats
            return $this->formatBooks($data['items'] ?? []);
            
        } catch (TransportExceptionInterface $e) {
            throw new \Exception('Erreur de connexion: ' . $e->getMessage());
        }
    }
    
    /**
     * Chercher par ISBN
     */
    public function searchByIsbn(string $isbn): ?array
    {
        // Nettoyer l'ISBN (enlever les tirets)
        $cleanIsbn = preg_replace('/[^0-9X]/', '', $isbn);
        
        $results = $this->searchBooks('isbn:' . $cleanIsbn, 1);
        
        return $results[0] ?? null;
    }
    
    /**
     * Chercher par auteur
     */
    public function searchByAuthor(string $author, int $maxResults = 10): array
    {
        return $this->searchBooks('inauthor:' . $author, $maxResults);
    }
    
    /**
     * Chercher par genre/catégorie
     */
    public function searchByCategory(string $category, int $maxResults = 10): array
    {
        return $this->searchBooks('subject:' . $category, $maxResults);
    }
    
    /**
     * Obtenir les détails d'un livre par son ID
     */
    public function getBookDetails(string $bookId): ?array
    {
        $url = self::BASE_URL . '/volumes/' . $bookId;
        
        try {
            $response = $this->client->request('GET', $url, [
                'query' => [
                    'key' => $this->apiKey
                ]
            ]);
            
            if ($response->getStatusCode() !== 200) {
                return null;
            }
            
            $data = $response->toArray();
            
            return $this->formatBook($data);
            
        } catch (TransportExceptionInterface $e) {
            return null;
        }
    }
    
    /**
     * Formater les livres pour l'affichage
     */
    private function formatBooks(array $items): array
    {
        $formatted = [];
        
        foreach ($items as $item) {
            $formatted[] = $this->formatBook($item);
        }
        
        return $formatted;
    }
    
    private function formatBook(array $bookData): array
    {
        $volumeInfo = $bookData['volumeInfo'] ?? [];
        $saleInfo = $bookData['saleInfo'] ?? [];
        
        // Extraire l'ISBN
        $isbn = null;
        $isbn13 = null;
        $isbn10 = null;
        
        foreach ($volumeInfo['industryIdentifiers'] ?? [] as $identifier) {
            if ($identifier['type'] === 'ISBN_13') {
                $isbn13 = $identifier['identifier'];
                $isbn = $isbn13;
            } elseif ($identifier['type'] === 'ISBN_10') {
                $isbn10 = $identifier['identifier'];
                $isbn = $isbn10;
            }
        }
        
        // Image
        $image = $volumeInfo['imageLinks']['thumbnail'] 
               ?? $volumeInfo['imageLinks']['smallThumbnail'] 
               ?? null;
        
        // Nettoyer l'URL de l'image (passer en HTTPS si nécessaire)
        if ($image && str_starts_with($image, 'http://')) {
            $image = 'https://' . substr($image, 7);
        }
        
        return [
            'id' => $bookData['id'] ?? null,
            'title' => $volumeInfo['title'] ?? 'Titre inconnu',
            'subtitle' => $volumeInfo['subtitle'] ?? '',
            'authors' => $volumeInfo['authors'] ?? ['Auteur inconnu'],
            'description' => $volumeInfo['description'] ?? 'Pas de description disponible.',
            'publishedDate' => $volumeInfo['publishedDate'] ?? null,
            'publisher' => $volumeInfo['publisher'] ?? null,
            'pageCount' => $volumeInfo['pageCount'] ?? 0,
            'categories' => $volumeInfo['categories'] ?? [],
            'language' => $volumeInfo['language'] ?? 'fr',
            'isbn' => $isbn,
            'isbn10' => $isbn10,
            'isbn13' => $isbn13,
            'image' => $image,
            'previewLink' => $volumeInfo['previewLink'] ?? null,
            'infoLink' => $volumeInfo['infoLink'] ?? null,
            'rating' => $volumeInfo['averageRating'] ?? 0,
            'ratingsCount' => $volumeInfo['ratingsCount'] ?? 0,
            'price' => $saleInfo['listPrice']['amount'] ?? null,
            'currency' => $saleInfo['listPrice']['currencyCode'] ?? 'EUR',
            'isFree' => ($saleInfo['saleability'] ?? '') === 'FREE',
            'isEbook' => ($saleInfo['isEbook'] ?? false) === true,
        ];
    }
    
    /**
     * Suggestions pour un club
     */
    public function suggestForClub(string $clubCategory, int $limit = 5): array
    {
        // Mapper les catégories de club vers des sujets de livres
        $categoryMap = [
            'romance' => 'love',
            'science-fiction' => 'science fiction',
            'fantasy' => 'fantasy',
            'polar' => 'mystery',
            'historique' => 'history',
            'biographie' => 'biography',
            'thriller' => 'thriller',
            'classique' => 'classic',
            'poésie' => 'poetry',
            'théâtre' => 'drama',
        ];
        
        $subject = $categoryMap[strtolower($clubCategory)] ?? $clubCategory;
        
        return $this->searchByCategory($subject, $limit);
    }
    
    /**
     * Vérifier si l'API fonctionne
     */
    public function testConnection(): bool
    {
        try {
            $this->searchBooks('test', 1);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}