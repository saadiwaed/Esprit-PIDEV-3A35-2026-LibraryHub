<?php

namespace App\Repository;

use App\Entity\Loan;
use App\Entity\Penalty;
use App\Entity\User;
use App\Enum\PaymentStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Penalty>
 */
class PenaltyRepository extends ServiceEntityRepository
{
    public const SORT_MEMBER_NAME_ASC = 'member_name_asc';
    public const SORT_AMOUNT_DESC = 'amount_desc';
    public const SORT_ISSUE_DATE_DESC = 'issue_desc';
    public const SORT_STATUS_PRIORITY = 'status_priority';
    public const SORT_WAIVED_LAST = 'waived_last';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Penalty::class);
    }

    /**
     * Find all unpaid penalties
     *
     * @return Penalty[]
     */
    public function findUnpaidPenalties(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', PaymentStatus::UNPAID)
            ->orderBy('p.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find penalties by loan ID
     *
     * @return Penalty[]
     */
    public function findByLoan(int $loanId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.loan = :loanId')
            ->setParameter('loanId', $loanId)
            ->orderBy('p.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Penalty[]
     */
    public function findForMember(User $member, int $limit = 200): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.loan', 'l')
            ->addSelect('l')
            ->andWhere('l.member = :member')
            ->setParameter('member', $member)
            ->addSelect(
                'CASE
                    WHEN p.status = :statusUnpaid THEN 0
                    WHEN p.status = :statusPartial THEN 1
                    WHEN p.status = :statusPaid THEN 2
                    ELSE 3
                END AS HIDDEN penaltyStatusPriority'
            )
            ->setParameter('statusUnpaid', PaymentStatus::UNPAID)
            ->setParameter('statusPartial', PaymentStatus::PARTIAL)
            ->setParameter('statusPaid', PaymentStatus::PAID)
            ->orderBy('penaltyStatusPriority', 'ASC')
            ->addOrderBy('p.waived', 'ASC')
            ->addOrderBy('p.issueDate', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOpenOverduePenaltyForLoan(Loan $loan): ?Penalty
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.loan = :loan')
            ->andWhere('p.waived = :waived')
            ->andWhere('p.status = :status')
            ->andWhere('p.notes LIKE :marker')
            ->setParameter('loan', $loan)
            ->setParameter('waived', false)
            ->setParameter('status', PaymentStatus::UNPAID)
            ->setParameter('marker', '%AUTO_OVERDUE_DAILY%')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasDailyPenaltyForDate(Loan $loan, \DateTimeImmutable $date): bool
    {
        $count = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.loan = :loan')
            ->andWhere('p.issueDate = :issueDate')
            ->andWhere('p.reason LIKE :reasonPrefix')
            ->setParameter('loan', $loan)
            ->setParameter('issueDate', \DateTime::createFromImmutable($date))
            ->setParameter('reasonPrefix', Penalty::DAILY_LATE_REASON_PREFIX . '%')
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function findLatestLatePenaltyForLoan(Loan $loan): ?Penalty
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.loan = :loan')
            ->andWhere('(p.reason = :lateReturn OR p.reason LIKE :dailyPrefix)')
            ->setParameter('loan', $loan)
            ->setParameter('lateReturn', Penalty::REASON_LATE_RETURN)
            ->setParameter('dailyPrefix', Penalty::DAILY_LATE_REASON_PREFIX . '%')
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findActiveLatePenaltyForLoan(Loan $loan): ?Penalty
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.loan = :loan')
            ->andWhere('(p.reason = :lateReturn OR p.reason LIKE :dailyPrefix)')
            ->andWhere('p.waived = :waived')
            ->andWhere('p.status = :unpaidStatus')
            ->setParameter('loan', $loan)
            ->setParameter('lateReturn', Penalty::REASON_LATE_RETURN)
            ->setParameter('dailyPrefix', Penalty::DAILY_LATE_REASON_PREFIX . '%')
            ->setParameter('waived', false)
            ->setParameter('unpaidStatus', PaymentStatus::UNPAID)
            ->orderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find waived penalties
     *
     * @return Penalty[]
     */
    public function findWaivedPenalties(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.waived = :waived')
            ->setParameter('waived', true)
            ->orderBy('p.issueDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unpaid penalties total amount
     */
    public function sumUnpaidPenalties(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.status = :status')
            ->andWhere('p.waived = :waived')
            ->setParameter('status', PaymentStatus::UNPAID)
            ->setParameter('waived', false)
            ->getQuery()
            ->getSingleScalarResult();

        return is_numeric($result) ? (float) $result : 0.0;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function findByFiltersAndSort(array $filters = [], ?string $sortBy = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.loan', 'l')
            ->addSelect('l')
            ->leftJoin('l.member', 'm')
            ->addSelect('m');

        switch ($sortBy) {
            case self::SORT_MEMBER_NAME_ASC:
                $qb->orderBy('m.lastName', 'ASC')
                    ->addOrderBy('m.firstName', 'ASC')
                    ->addOrderBy('p.id', 'DESC');
                break;

            case self::SORT_AMOUNT_DESC:
                $qb->orderBy('p.amount', 'DESC')
                    ->addOrderBy('p.id', 'DESC');
                break;

            case self::SORT_ISSUE_DATE_DESC:
                $qb->orderBy('p.issueDate', 'DESC')
                    ->addOrderBy('p.id', 'DESC');
                break;

            case self::SORT_STATUS_PRIORITY:
                $qb->addSelect(
                    'CASE
                        WHEN p.status = :statusUnpaid THEN 0
                        WHEN p.status = :statusPartial THEN 1
                        WHEN p.status = :statusPaid THEN 2
                        ELSE 3
                    END AS HIDDEN penaltyStatusPriority'
                )
                    ->setParameter('statusUnpaid', PaymentStatus::UNPAID)
                    ->setParameter('statusPartial', PaymentStatus::PARTIAL)
                    ->setParameter('statusPaid', PaymentStatus::PAID)
                    ->orderBy('penaltyStatusPriority', 'ASC')
                    ->addOrderBy('p.issueDate', 'DESC')
                    ->addOrderBy('p.id', 'DESC');
                break;

            case self::SORT_WAIVED_LAST:
                $qb->addSelect('CASE WHEN p.waived = true THEN 1 ELSE 0 END AS HIDDEN waivedOrder')
                    ->orderBy('waivedOrder', 'ASC')
                    ->addOrderBy('p.issueDate', 'DESC')
                    ->addOrderBy('p.id', 'DESC');
                break;

            default:
                $qb->orderBy('p.issueDate', 'DESC')
                    ->addOrderBy('p.id', 'DESC');
                break;
        }

        return $qb;
    }

    public function getFilteredSortedQueryBuilder(string $sort = '', string $direction = 'asc'): \Doctrine\ORM\QueryBuilder
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';
        $legacyMap = [
            'member_asc' => self::SORT_MEMBER_NAME_ASC,
            'amount_desc' => self::SORT_AMOUNT_DESC,
            'issueDate_desc' => self::SORT_ISSUE_DATE_DESC,
            'status_asc' => self::SORT_STATUS_PRIORITY,
            'waived_asc' => self::SORT_WAIVED_LAST,
        ];

        $legacyKey = sprintf('%s_%s', $sort, $direction);
        $sortBy = $legacyMap[$legacyKey] ?? null;

        return $this->findByFiltersAndSort([], $sortBy);
    }
}

