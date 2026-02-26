<?php

namespace App\Controller;

use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Enum\PaymentStatus;
use App\Repository\LoanRepository;
use App\Repository\PenaltyRepository;
use App\Repository\RenewalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StatsController extends AbstractController
{
    /*
    |--------------------------------------------------------------------------
    | BOOK & AUTHOR STATS (FRONT + ADMIN)
    |--------------------------------------------------------------------------
    */

    #[Route('/stats', name: 'app_stats')]
    public function index(
        BookRepository $bookRepository,
        AuthorRepository $authorRepository
    ): Response {
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

        return $this->render('stats/index2.html.twig', [
            'booksByCategory' => $booksByCategory,
            'booksByStatus' => $booksByStatus,
            'monthsLabels' => array_keys($months),
            'monthsData' => array_values($months),
            'booksByAuthor' => $booksByAuthor,
            'authorsByNationality' => $authorsByNationality,
        ]);
    }

    #[Route('/stats/admin', name: 'app_stats_admin')]
    public function index_admin(
        BookRepository $bookRepository,
        AuthorRepository $authorRepository
    ): Response {
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

    /*
    |--------------------------------------------------------------------------
    | LOAN / RENEWAL / PENALTY STATS (ADMIN + GESTION)
    |--------------------------------------------------------------------------
    */

    #[Route('/admin/stats', name: 'app_stats_index', methods: ['GET'])]
    #[Route('/gestion/stats', name: 'app_stats_index_alt', methods: ['GET'])]
    public function indexLoanStats(
        Request $request,
        LoanRepository $loanRepository,
        RenewalRepository $renewalRepository,
        PenaltyRepository $penaltyRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $filterForm = $this->createFormBuilder(null, [
            'method' => 'GET',
            'csrf_protection' => false,
        ])
            ->add('dateFrom', DateType::class, [
                'label' => 'Du',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->add('dateTo', DateType::class, [
                'label' => 'Au',
                'required' => false,
                'widget' => 'single_text',
            ])
            ->getForm();

        $filterForm->handleRequest($request);
        $filters = $filterForm->getData() ?? [];
        $dateFrom = $filters['dateFrom'] ?? null;
        $dateTo = $filters['dateTo'] ?? null;

        $loanCounts = $loanRepository->getLoanCounts($dateFrom, $dateTo);
        $averageDuration = $loanRepository->getAverageLoanDurationDays($dateFrom, $dateTo);
        $mostBorrowed = $loanRepository->getMostBorrowedBookCopy($dateFrom, $dateTo);
        $topMembers = $loanRepository->getTopMembersByLoans(5, $dateFrom, $dateTo);

        $periodLabel = ($dateFrom || $dateTo) ? 'Prêts sur la période' : 'Prêts ce mois';
        $periodCount = ($dateFrom || $dateTo)
            ? $loanRepository->countLoansBetween($dateFrom, $dateTo)
            : $loanRepository->countLoansBetween(
                new \DateTimeImmutable('first day of this month 00:00:00'),
                new \DateTimeImmutable('last day of this month 23:59:59')
            );

        $averageRenewals = $loanRepository->getAverageRenewalCount($dateFrom, $dateTo);
        $mostRenewedLoan = $loanRepository->getMostRenewedLoan($dateFrom, $dateTo);

        $totalRenewals = (int) $renewalRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $totalPenalties = (int) $penaltyRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->render('stats/index.html.twig', [
            'filterForm' => $filterForm->createView(),
            'loanCounts' => $loanCounts,
            'periodLabel' => $periodLabel,
            'periodCount' => $periodCount,
            'averageDuration' => $averageDuration,
            'mostBorrowed' => $mostBorrowed,
            'topMembers' => $topMembers,
            'averageRenewals' => $averageRenewals,
            'mostRenewedLoan' => $mostRenewedLoan,
            'totalRenewals' => $totalRenewals,
            'totalPenalties' => $totalPenalties,
            'lastUpdate' => new \DateTimeImmutable(),
        ]);
    }
}