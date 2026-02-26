<?php

namespace App\Controller;

use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StatsController extends AbstractController
{
    #[Route('/stats', name: 'app_stats')]
public function index(BookRepository $bookRepository, AuthorRepository $authorRepository): Response
{
    // BOOK STATS
    $booksByCategory = $bookRepository->countBooksByCategory();
    $booksByStatus = $bookRepository->countBooksByStatus();

    // MONTHS
    $books = $bookRepository->findAllBooksForStats();
    $months = [];

    foreach ($books as $book) {
        $date = $book['createdAt'];
        if ($date instanceof \DateTimeInterface) {
            $month = $date->format('Y-m');
            if (!isset($months[$month])) {
                $months[$month] = 0;
            }
            $months[$month]++;
        }
    }
    ksort($months);

    // AUTHOR STATS
    $booksByAuthor = $authorRepository->countBooksByAuthor();
    $authorsByNationality = $authorRepository->countAuthorsByNationality();

    return $this->render('stats/index.html.twig', [
        'booksByCategory' => $booksByCategory,
        'booksByStatus' => $booksByStatus,
        'monthsLabels' => array_keys($months),
        'monthsData' => array_values($months),
        'booksByAuthor' => $booksByAuthor,
        'authorsByNationality' => $authorsByNationality,
    ]);
}
#[Route('/stats/admin', name: 'app_stats_admin')]
public function index_admin(BookRepository $bookRepository, AuthorRepository $authorRepository): Response
{
    // BOOK STATS
    $booksByCategory = $bookRepository->countBooksByCategory();
    $booksByStatus = $bookRepository->countBooksByStatus();

    // MONTHS
    $books = $bookRepository->findAllBooksForStats();
    $months = [];

    foreach ($books as $book) {
        $date = $book['createdAt'];
        if ($date instanceof \DateTimeInterface) {
            $month = $date->format('Y-m');
            if (!isset($months[$month])) {
                $months[$month] = 0;
            }
            $months[$month]++;
        }
    }
    ksort($months);

    // AUTHOR STATS
    $booksByAuthor = $authorRepository->countBooksByAuthor();
    $authorsByNationality = $authorRepository->countAuthorsByNationality();

    return $this->render('stats/index_admin.html.twig', [
        'booksByCategory' => $booksByCategory,
        'booksByStatus' => $booksByStatus,
        'monthsLabels' => array_keys($months),
        'monthsData' => array_values($months),
        'booksByAuthor' => $booksByAuthor,
        'authorsByNationality' => $authorsByNationality,
    ]);
}

}
