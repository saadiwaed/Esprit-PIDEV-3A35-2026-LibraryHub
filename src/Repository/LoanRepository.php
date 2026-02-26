<?php

namespace App\Repository;

use App\Entity\Loan;
use App\Enum\LoanStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Loan>
 */
class LoanRepository extends ServiceEntityRepository
{
    public const SORT_MEMBER_NAME_ASC = 'member_name_asc';
    public const SORT_CHECKOUT_DESC = 'checkout_desc';
    public const SORT_DUE_ASC = 'due_asc';
    public const SORT_RETURN_DESC = 'return_desc';
    public const SORT_STATUS_PRIORITY = 'status_priority';
    public const SORT_ID_DESC = 'id_desc';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Loan::class);
    }

    /**
     * Find all active loans (not yet returned)
     *
     * @return Loan[]
     */
    public function findActiveLoan(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.status = :status')
            ->setParameter('status', LoanStatus::ACTIVE)
            ->orderBy('l.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all overdue loans
     *
     * @return Loan[]
     */
    public function findOverdueLoans(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.status = :status')
            ->setParameter('status', LoanStatus::OVERDUE)
            ->orderBy('l.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find loans that must transition from ACTIVE to OVERDUE.
     *
     * @return Loan[]
     */
    public function findOverdueCandidates(?\DateTimeInterface $reference = null): array
    {
        $today = \DateTimeImmutable::createFromInterface($reference ?? new \DateTimeImmutable('today'))->setTime(0, 0, 0);

        return $this->createQueryBuilder('l')
            ->andWhere('l.status = :activeStatus')
            ->andWhere('l.returnDate IS NULL')
            ->andWhere('l.dueDate < :today')
            ->setParameter('activeStatus', LoanStatus::ACTIVE)
            ->setParameter('today', $today)
            ->orderBy('l.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function createCurrentlyOverdueQueryBuilder(?\DateTimeInterface $reference = null): QueryBuilder
    {
        $today = \DateTimeImmutable::createFromInterface($reference ?? new \DateTimeImmutable('today'))->setTime(0, 0, 0);

        return $this->createQueryBuilder('l')
            ->andWhere('l.returnDate IS NULL')
            ->andWhere('l.dueDate < :today')
            ->setParameter('today', $today)
            ->orderBy('l.dueDate', 'ASC');
    }

    /**
     * @return Loan[]
     */
    public function findOverdueOpenLoansForPenalty(?\DateTimeInterface $reference = null): array
    {
        $today = \DateTimeImmutable::createFromInterface($reference ?? new \DateTimeImmutable('today'))->setTime(0, 0, 0);

        return $this->createQueryBuilder('l')
            ->andWhere('l.status = :overdueStatus')
            ->andWhere('l.returnDate IS NULL')
            ->andWhere('l.dueDate < :today')
            ->setParameter('overdueStatus', LoanStatus::OVERDUE)
            ->setParameter('today', $today)
            ->orderBy('l.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findCurrentlyOverdueUnreturned(): QueryBuilder
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.status = :overdueStatus')
            ->andWhere('l.returnDate IS NULL')
            ->andWhere('l.dueDate < CURRENT_DATE()')
            ->setParameter('overdueStatus', LoanStatus::OVERDUE)
            ->orderBy('l.dueDate', 'ASC');
    }

    /**
     * Find open loans for status refresh (ACTIVE/OVERDUE and not returned yet).
     *
     * @return Loan[]
     */
    public function findOpenLoansForStatusRefresh(): array
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.returnDate IS NULL')
            ->andWhere('l.status IN (:refreshableStatuses)')
            ->setParameter('refreshableStatuses', [LoanStatus::ACTIVE, LoanStatus::OVERDUE])
            ->getQuery()
            ->getResult();
    }

    /**
     * Find loans by member
     *
     * @return Loan[]
     */
    public function findByMember($memberId): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.member = :memberId')
            ->setParameter('memberId', $memberId)
            ->orderBy('l.checkoutTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find loans by book copy
     *
     * @return Loan[]
     */
    public function findByBookCopy($bookCopyId): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.bookCopy = :bookCopyId')
            ->setParameter('bookCopyId', $bookCopyId)
            ->orderBy('l.checkoutTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total loans
     */
    public function countTotalLoans(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count active loans
     */
    public function countActiveLoans(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.status = :status')
            ->setParameter('status', LoanStatus::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count overdue loans
     */
    public function countOverdueLoans(): int
    {
        return (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.status = :status')
            ->setParameter('status', LoanStatus::OVERDUE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Build a filtered query for loans list.
     *
     * @param array{
     *     search?: string|null,
     *     filterType?: string|null,
     *     memberSearch?: string|null,
     *     dateFrom?: \DateTimeInterface|null,
     *     dateTo?: \DateTimeInterface|null
     * } $filters
     */
    public function findByFilters(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.member', 'm')
            ->addSelect('m')
            ->leftJoin('l.bookCopy', 'bc')
            ->addSelect('bc')
            ->orderBy('l.checkoutTime', 'DESC');

        $filterType = $filters['filterType'] ?? null;
        $search = $filters['search'] ?? null;
        $memberSearch = $filters['memberSearch'] ?? null;
        $dateFrom = $filters['dateFrom'] ?? null;
        $dateTo = $filters['dateTo'] ?? null;

        if ($filterType === 'member') {
            $keyword = $memberSearch ?: $search;
            if (is_string($keyword) && trim($keyword) !== '') {
                $keyword = mb_strtolower(trim($keyword));
                $qb->andWhere(
                    'LOWER(m.firstName) LIKE :kw
                    OR LOWER(m.lastName) LIKE :kw
                    OR LOWER(CONCAT(m.firstName, \' \', m.lastName)) LIKE :kw
                    OR LOWER(m.email) LIKE :kw'
                )
                    ->setParameter('kw', '%' . $keyword . '%');
            }
        }

        if ($filterType === 'checkout' || $filterType === 'return') {
            $dateField = $filterType === 'checkout' ? 'l.checkoutTime' : 'l.returnDate';
            if ($dateFrom instanceof \DateTimeInterface) {
                $start = \DateTimeImmutable::createFromInterface($dateFrom)->setTime(0, 0, 0);
                if ($dateTo instanceof \DateTimeInterface) {
                    $end = \DateTimeImmutable::createFromInterface($dateTo)->setTime(23, 59, 59);
                    $qb->andWhere(sprintf('%s BETWEEN :startDate AND :endDate', $dateField))
                        ->setParameter('startDate', $start)
                        ->setParameter('endDate', $end);
                } else {
                    $qb->andWhere(sprintf('%s >= :startDate', $dateField))
                        ->setParameter('startDate', $start);
                }
            }

            if ($filterType === 'return') {
                $qb->andWhere('l.returnDate IS NOT NULL');
            }
        }

        return $qb;
    }

    /**
     * Count loans for the same filters as findByFilters.
     *
     * @param array{
     *     search?: string|null,
     *     filterType?: string|null,
     *     memberSearch?: string|null,
     *     dateFrom?: \DateTimeInterface|null,
     *     dateTo?: \DateTimeInterface|null
     * } $filters
     */
    public function countByFilters(array $filters): int
    {
        $qb = $this->findByFilters($filters);
        $qb->resetDQLPart('orderBy');
        $qb->select('COUNT(DISTINCT l.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Query builder with filters and user-selected sorting for list page.
     *
     * @param array{
     *     search?: string|null,
     *     filterType?: string|null,
     *     memberSearch?: string|null,
     *     dateFrom?: \DateTimeInterface|null,
     *     dateTo?: \DateTimeInterface|null
     * } $filters
     */
    public function findByFiltersAndSort(array $filters = [], ?string $sortBy = null): QueryBuilder
    {
        $qb = $this->findByFilters($filters);
        $qb->resetDQLPart('orderBy');
        switch ($sortBy) {
            case self::SORT_MEMBER_NAME_ASC:
                $qb->orderBy('m.lastName', 'ASC')
                    ->addOrderBy('m.firstName', 'ASC')
                    ->addOrderBy('l.id', 'DESC');
                break;

            case self::SORT_CHECKOUT_DESC:
                $qb->orderBy('l.checkoutTime', 'DESC')
                    ->addOrderBy('l.id', 'DESC');
                break;

            case self::SORT_DUE_ASC:
                $qb->orderBy('l.dueDate', 'ASC')
                    ->addOrderBy('l.id', 'DESC');
                break;

            case self::SORT_RETURN_DESC:
                $qb->orderBy('l.returnDate', 'DESC')
                    ->addOrderBy('l.id', 'DESC');
                break;

            case self::SORT_STATUS_PRIORITY:
                $qb->addSelect(
                    'CASE
                        WHEN l.status = :overdueStatus THEN 0
                        WHEN l.status = :activeStatus THEN 1
                        WHEN l.status = :returnedStatus THEN 2
                        ELSE 3
                    END AS HIDDEN loanStatusPriority'
                )
                    ->setParameter('overdueStatus', LoanStatus::OVERDUE)
                    ->setParameter('activeStatus', LoanStatus::ACTIVE)
                    ->setParameter('returnedStatus', LoanStatus::RETURNED)
                    ->orderBy('loanStatusPriority', 'ASC')
                    ->addOrderBy('l.dueDate', 'ASC')
                    ->addOrderBy('l.id', 'DESC');
                break;

            case self::SORT_ID_DESC:
                $qb->orderBy('l.id', 'DESC');
                break;

            default:
                $qb->addSelect(
                    'CASE
                        WHEN l.status = :defaultOverdueStatus THEN 0
                        WHEN l.status = :defaultActiveStatus THEN 1
                        WHEN l.status = :defaultReturnedStatus THEN 2
                        ELSE 3
                    END AS HIDDEN defaultLoanPriority'
                )
                    ->setParameter('defaultOverdueStatus', LoanStatus::OVERDUE)
                    ->setParameter('defaultActiveStatus', LoanStatus::ACTIVE)
                    ->setParameter('defaultReturnedStatus', LoanStatus::RETURNED)
                    ->orderBy('defaultLoanPriority', 'ASC')
                    ->addOrderBy('l.dueDate', 'ASC')
                    ->addOrderBy('l.id', 'DESC');
                break;
        }

        return $qb;
    }

    public function getFilteredSortedQueryBuilder(array $filters = [], string $sort = '', string $direction = 'asc'): QueryBuilder
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $legacyMap = [
            'member_asc' => self::SORT_MEMBER_NAME_ASC,
            'checkout_desc' => self::SORT_CHECKOUT_DESC,
            'due_asc' => self::SORT_DUE_ASC,
            'return_desc' => self::SORT_RETURN_DESC,
            'id_desc' => self::SORT_ID_DESC,
            'status_asc' => self::SORT_STATUS_PRIORITY,
        ];

        $legacyKey = sprintf('%s_%s', $sort, $direction);
        $sortBy = $legacyMap[$legacyKey] ?? null;

        return $this->findByFiltersAndSort($filters, $sortBy);
    }

    public function getLoanCounts(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        return [
            'total' => $this->countAllLoans($from, $to),
            'active' => $this->countByStatus(LoanStatus::ACTIVE, $from, $to),
            'overdue' => $this->countByStatus(LoanStatus::OVERDUE, $from, $to),
            'returned' => $this->countByStatus(LoanStatus::RETURNED, $from, $to),
        ];
    }

    public function countLoansBetween(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): int
    {
        return $this->countAllLoans($from, $to);
    }

    public function getAverageLoanDurationDays(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        $qb = $this->createQueryBuilder('l')
            ->select('AVG(DATE_DIFF(l.returnDate, l.checkoutTime))')
            ->andWhere('l.returnDate IS NOT NULL');

        $this->applyDateRange($qb, 'l.checkoutTime', $from, $to);

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

    public function getMostBorrowedBookCopy(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): ?array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('bc.id AS bookCopyId, COUNT(l.id) AS loanCount')
            ->leftJoin('l.bookCopy', 'bc')
            ->groupBy('bc.id')
            ->orderBy('loanCount', 'DESC')
            ->setMaxResults(1);

        $this->applyDateRange($qb, 'l.checkoutTime', $from, $to);

        $result = $qb->getQuery()->getOneOrNullResult();

        if (!$result) {
            return null;
        }

        return [
            'bookCopyId' => (int) $result['bookCopyId'],
            'loanCount' => (int) $result['loanCount'],
        ];
    }

    public function getTopMembersByLoans(int $limit = 5, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('m.id AS memberId, m.firstName, m.lastName, COUNT(l.id) AS loanCount')
            ->leftJoin('l.member', 'm')
            ->groupBy('m.id')
            ->orderBy('loanCount', 'DESC')
            ->setMaxResults($limit);

        $this->applyDateRange($qb, 'l.checkoutTime', $from, $to);

        return $qb->getQuery()->getArrayResult();
    }

    public function getAverageRenewalCount(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        $qb = $this->createQueryBuilder('l')
            ->select('AVG(l.renewalCount)');

        $this->applyDateRange($qb, 'l.checkoutTime', $from, $to);

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

    public function getMostRenewedLoan(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): ?Loan
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.member', 'm')
            ->addSelect('m')
            ->leftJoin('l.bookCopy', 'bc')
            ->addSelect('bc')
            ->orderBy('l.renewalCount', 'DESC')
            ->setMaxResults(1);

        $this->applyDateRange($qb, 'l.checkoutTime', $from, $to);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getMonthlyLoanCounts(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('l.checkoutTime AS checkoutTime')
            ->andWhere('l.checkoutTime >= :start')
            ->andWhere('l.checkoutTime <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            if (!isset($row['checkoutTime']) || !$row['checkoutTime'] instanceof \DateTimeInterface) {
                continue;
            }
            $key = $row['checkoutTime']->format('Y-m');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    private function countAllLoans(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)');

        $this->applyDateRange($qb, 'l.checkoutTime', $from, $to);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function countByStatus(LoanStatus $status, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.status = :status')
            ->setParameter('status', $status);

        $this->applyDateRange($qb, 'l.checkoutTime', $from, $to);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyDateRange(QueryBuilder $qb, string $field, ?\DateTimeInterface $from, ?\DateTimeInterface $to): void
    {
        if ($from instanceof \DateTimeInterface) {
            $qb->andWhere(sprintf('%s >= :fromDate', $field))
                ->setParameter('fromDate', \DateTimeImmutable::createFromInterface($from)->setTime(0, 0, 0));
        }

        if ($to instanceof \DateTimeInterface) {
            $qb->andWhere(sprintf('%s <= :toDate', $field))
                ->setParameter('toDate', \DateTimeImmutable::createFromInterface($to)->setTime(23, 59, 59));
        }
    }
}
