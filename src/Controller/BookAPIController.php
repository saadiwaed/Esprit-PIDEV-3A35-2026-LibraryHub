<?php

namespace App\Controller;

use App\Service\GoogleBooksService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/GoogleAPIbooks')]
class BookAPIController extends AbstractController
{
    #[Route('/search', name: 'books_search', methods: ['GET'])]
    public function search(Request $request, GoogleBooksService $booksService): Response
    {
        $query = $request->query->get('q', '');
        
        if (empty($query)) {
            return $this->render('Googlebooks/search.html.twig', [
                'books' => [],
                'query' => '',
                'error' => 'Entrez un terme de recherche'
            ]);
        }
        
        try {
            $books = $booksService->searchBooks($query, 12);
            
            return $this->render('Googlebooks/search.html.twig', [
                'books' => $books,
                'query' => $query,
                'count' => count($books)
            ]);
            
        } catch (\Exception $e) {
            return $this->render('Googlebooks/search.html.twig', [
                'books' => [],
                'query' => $query,
                'error' => 'Erreur lors de la recherche: ' . $e->getMessage()
            ]);
        }
    }
    
    #[Route('/api/search', name: 'books_api_search', methods: ['GET'])]
    public function apiSearch(Request $request, GoogleBooksService $booksService): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty($query)) {
            return $this->json(['error' => 'Query parameter "q" is required'], 400);
        }
        
        try {
            $books = $booksService->searchBooks($query, 10);
            
            return $this->json([
                'success' => true,
                'count' => count($books),
                'books' => $books
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    #[Route('/isbn/{isbn}', name: 'books_by_isbn', methods: ['GET'])]
    public function byIsbn(string $isbn, GoogleBooksService $booksService): Response
    {
        $book = $booksService->searchByIsbn($isbn);
        
        if (!$book) {
            return $this->render('books/not_found.html.twig', [
                'isbn' => $isbn
            ]);
        }
        
        return $this->render('books/detail.html.twig', [
            'book' => $book
        ]);
    }
    
    #[Route('/club/{id}/suggestions', name: 'club_book_suggestions', methods: ['GET'])]
    public function clubSuggestions(int $id, GoogleBooksService $booksService): JsonResponse
    {
        // Récupérer le club depuis la base
        // $club = $this->getDoctrine()->getRepository(Club::class)->find($id);
        
        // Pour l'exemple, on utilise une catégorie fixe
        $category = 'science-fiction'; // À remplacer par $club->getCategory()
        
        $suggestions = $booksService->suggestForClub($category, 6);
        
        return $this->json([
            'success' => true,
            'club_id' => $id,
            'category' => $category,
            'suggestions' => $suggestions
        ]);
    }
    
    #[Route('/test-api', name: 'test_api', methods: ['GET'])]
    public function testApi(GoogleBooksService $booksService): Response
    {
        $isWorking = $booksService->testConnection();
        
        return $this->render('books/test.html.twig', [
            'isWorking' => $isWorking,
            'apiKey' => $_ENV['GOOGLE_BOOKS_API_KEY'] ?? 'Non configurée'
        ]);
    }
}