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
            $month = $date->format('Y-m');
            if (!isset($months[$month])) {
                $months[$month] = 0;
            }
            $months[$month]++;
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
            $month = $date->format('Y-m');
            if (!isset($months[$month])) {
                $months[$month] = 0;
            }
            $months[$month]++;
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

        $periodLabel = ($dateFrom || $dateTo) ? 'PrÃªts sur la periode' : 'PrÃªts ce mois';
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

        $totalDue = (float) $penaltyRepository->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('statuses', [PaymentStatus::UNPAID, PaymentStatus::PARTIAL])
            ->getQuery()
            ->getSingleScalarResult();

        $totalPaid = (float) $penaltyRepository->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->andWhere('p.status = :status')
            ->setParameter('status', PaymentStatus::PAID)
            ->getQuery()
            ->getSingleScalarResult();

        $totalWaived = (float) $penaltyRepository->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.amount), 0)')
            ->andWhere('p.waived = :waived')
            ->setParameter('waived', true)
            ->getQuery()
            ->getSingleScalarResult();

        $averagePenalty = (float) $penaltyRepository->createQueryBuilder('p')
            ->select('COALESCE(AVG(p.amount), 0)')
            ->getQuery()
            ->getSingleScalarResult();

        $penaltiesThisPeriod = (int) $penaltyRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.issueDate >= :from')
            ->andWhere('p.issueDate <= :to')
            ->setParameter('from', ($dateFrom ?: new \DateTimeImmutable('first day of this month 00:00:00'))->setTime(0, 0, 0))
            ->setParameter('to', ($dateTo ?: new \DateTimeImmutable('last day of this month 23:59:59'))->setTime(23, 59, 59))
            ->getQuery()
            ->getSingleScalarResult();

        [$monthLabels, $loanMonthlyData] = $this->buildMonthlySeries(
            $loanRepository->getMonthlyLoanCounts(
                $this->getChartStart($dateFrom, $dateTo),
                $this->getChartEnd($dateFrom, $dateTo)
            ),
            $this->getChartStart($dateFrom, $dateTo),
            $this->getChartEnd($dateFrom, $dateTo)
        );

        $penaltyMonthlyCounts = $this->getMonthlyPenaltyCounts(
            $entityManager,
            $this->getChartStart($dateFrom, $dateTo),
            $this->getChartEnd($dateFrom, $dateTo)
        );
        [$penaltyMonthLabels, $penaltyMonthlyData] = $this->buildMonthlySeries(
            $penaltyMonthlyCounts,
            $this->getChartStart($dateFrom, $dateTo),
            $this->getChartEnd($dateFrom, $dateTo)
        );

        $chartLoansStatusData = [
            'labels' => ['Actifs', 'En retard', 'Rendus'],
            'datasets' => [[
                'data' => [
                    $loanCounts['active'],
                    $loanCounts['overdue'],
                    $loanCounts['returned'],
                ],
                'backgroundColor' => ['#0d6efd', '#ffc107', '#198754'],
            ]],
        ];

        $chartLoansMonthlyData = [
            'labels' => $monthLabels,
            'datasets' => [[
                'label' => 'Nombre de prÃªts',
                'data' => $loanMonthlyData,
                'backgroundColor' => '#0d6efd',
            ]],
        ];

        $chartPenaltiesMonthlyData = [
            'labels' => $penaltyMonthLabels,
            'datasets' => [[
                'label' => 'Amendes emises',
                'data' => $penaltyMonthlyData,
                'borderColor' => '#dc3545',
                'backgroundColor' => 'rgba(220,53,69,0.2)',
                'fill' => true,
            ]],
        ];

        $chartPenaltyAmountsData = [
            'labels' => ['Paye', 'Impaye/Partiel', 'Annule'],
            'datasets' => [[
                'data' => [$totalPaid, $totalDue, $totalWaived],
                'backgroundColor' => ['#198754', '#ffc107', '#6c757d'],
            ]],
        ];

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

    private function getChartStart(?\DateTimeInterface $from, ?\DateTimeInterface $to): \DateTimeImmutable
    {
        if ($from instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($from)->modify('first day of this month')->setTime(0, 0, 0);
        }

        if ($to instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($to)->modify('-11 months')->modify('first day of this month')->setTime(0, 0, 0);
        }

        return (new \DateTimeImmutable('first day of this month 00:00:00'))->modify('-11 months');
    }

    private function getChartEnd(?\DateTimeInterface $from, ?\DateTimeInterface $to): \DateTimeImmutable
    {
        if ($to instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($to)->modify('last day of this month')->setTime(23, 59, 59);
        }

        return new \DateTimeImmutable('last day of this month 23:59:59');
    }

    /**
     * @param array<string, int> $counts
     * @return array{0: list<string>, 1: list<int>}
     */
    private function buildMonthlySeries(array $counts, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $labels = [];
        $data = [];
        $cursor = $start->modify('first day of this month');
        $endCursor = $end->modify('first day of this month');

        while ($cursor <= $endCursor) {
            $key = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $data[] = $counts[$key] ?? 0;
            $cursor = $cursor->modify('+1 month');
        }

        return [$labels, $data];
    }

    /**
     * @return array<string, int>
     */
    private function getMonthlyPenaltyCounts(
        EntityManagerInterface $entityManager,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end
    ): array {
        $rows = $entityManager->createQueryBuilder()
            ->select('p.issueDate AS issueDate')
            ->from('App\\Entity\\Penalty', 'p')
            ->andWhere('p.issueDate >= :start')
            ->andWhere('p.issueDate <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            if (!isset($row['issueDate']) || !$row['issueDate'] instanceof \DateTimeInterface) {
                continue;
            }
            $key = $row['issueDate']->format('Y-m');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }
}


