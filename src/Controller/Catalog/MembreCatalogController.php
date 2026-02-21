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

#[Route('/accueil/membre')]
final class MembreCatalogController extends AbstractController
{
    #[Route(name: 'membre_catalog_index', methods: ['GET'])]
    public function index(
        CategoryRepository $categoryRepository,
        AuthorRepository $authorRepository,
        BookRepository $bookRepository
    ): Response {
        return $this->render('catalog/frontoffice/index.html.twig', [
            'categories_count' => $categoryRepository->count([]),
            'authors_count' => $authorRepository->count([]),
            'books_count' => $bookRepository->count([]),
            'area' => 'membre',
        ]);
    }

    #[Route('/categories', name: 'membre_category_index', methods: ['GET'])]
    public function categoryIndex(CategoryRepository $repository): Response
    {
        return $this->render('catalog/frontoffice/category/index.html.twig', [
            'categories' => $repository->findAllOrderedByName(),
            'area' => 'membre',
        ]);
    }

    #[Route('/categories/{id}', name: 'membre_category_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function categoryShow(Category $category): Response
    {
        return $this->render('catalog/frontoffice/category/show.html.twig', [
            'category' => $category,
            'area' => 'membre',
        ]);
    }

    #[Route('/authors', name: 'membre_author_index', methods: ['GET'])]
    public function authorIndex(AuthorRepository $repository): Response
    {
        return $this->render('catalog/frontoffice/author/index.html.twig', [
            'authors' => $repository->findBy([], ['lastname' => 'ASC', 'firstname' => 'ASC']),
            'area' => 'membre',
        ]);
    }

    #[Route('/authors/{id}', name: 'membre_author_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function authorShow(Author $author): Response
    {
        return $this->render('catalog/frontoffice/author/show.html.twig', [
            'author' => $author,
            'area' => 'membre',
        ]);
    }

    #[Route('/books', name: 'membre_book_index', methods: ['GET'])]
    public function bookIndex(BookRepository $repository): Response
    {
        return $this->render('catalog/frontoffice/book/index.html.twig', [
            'books' => $repository->findBy([], ['createdAt' => 'DESC']),
            'area' => 'membre',
        ]);
    }

    #[Route('/books/{id}', name: 'membre_book_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function bookShow(Book $book): Response
    {
        return $this->render('catalog/frontoffice/book/show.html.twig', [
            'book' => $book,
            'area' => 'membre',
        ]);
    }
}
