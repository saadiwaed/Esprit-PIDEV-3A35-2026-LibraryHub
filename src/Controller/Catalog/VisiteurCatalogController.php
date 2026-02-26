<?php

namespace App\Controller\Catalog;

use App\Entity\Author;
use App\Entity\Book;
use App\Entity\Category;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/accueil/visiteur')]
final class VisiteurCatalogController extends AbstractController
{
    #[Route(name: 'visiteur_catalog_index', methods: ['GET'])]
    public function index(
        CategoryRepository $categoryRepository,
        AuthorRepository $authorRepository,
        BookRepository $bookRepository
    ): Response {
        return $this->render('catalog/frontoffice/index.html.twig', [
            'categories_count' => $categoryRepository->count([]),
            'authors_count' => $authorRepository->count([]),
            'books_count' => $bookRepository->count([]),
            'area' => 'visiteur',
        ]);
    }

    #[Route('/categories', name: 'visiteur_category_index', methods: ['GET'])]
    public function categoryIndex(CategoryRepository $repository): Response
    {
        return $this->render('catalog/frontoffice/category/index.html.twig', [
            'categories' => $repository->findAllOrderedByName(),
            'area' => 'visiteur',
        ]);
    }

    #[Route('/categories/{id}', name: 'visiteur_category_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function categoryShow(Category $category): Response
    {
        return $this->render('catalog/frontoffice/category/show.html.twig', [
            'category' => $category,
            'area' => 'visiteur',
        ]);
    }

    #[Route('/authors', name: 'visiteur_author_index', methods: ['GET'])]
    public function authorIndex(AuthorRepository $repository): Response
    {
        return $this->render('catalog/frontoffice/author/index.html.twig', [
            'authors' => $repository->findBy([], ['lastname' => 'ASC', 'firstname' => 'ASC']),
            'area' => 'visiteur',
        ]);
    }

    #[Route('/authors/{id}', name: 'visiteur_author_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function authorShow(Author $author): Response
    {
        return $this->render('catalog/frontoffice/author/show.html.twig', [
            'author' => $author,
            'area' => 'visiteur',
        ]);
    }

    #[Route('/books', name: 'visiteur_book_index', methods: ['GET'])]
    public function bookIndex(BookRepository $repository): Response
    {
        return $this->render('catalog/frontoffice/book/index.html.twig', [
            'books' => $repository->findBy([], ['createdAt' => 'DESC']),
            'area' => 'visiteur',
        ]);
    }

    #[Route('/books/{id}', name: 'visiteur_book_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function bookShow(Book $book): Response
    {
        return $this->render('catalog/frontoffice/book/show.html.twig', [
            'book' => $book,
            'area' => 'visiteur',
        ]);
    }
}
