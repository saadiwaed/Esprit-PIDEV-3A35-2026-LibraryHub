<?php

namespace App\Repository;

use App\Entity\Loan;
use App\Entity\RenewalRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RenewalRequest>
 */
final class RenewalRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RenewalRequest::class);
    }

    public function createAdminListQueryBuilder(?string $status = RenewalRequest::STATUS_PENDING): QueryBuilder
    {
        $qb = $this->createQueryBuilder('rr')
            ->leftJoin('rr.member', 'm')
            ->addSelect('m')
            ->leftJoin('rr.loan', 'l')
            ->addSelect('l')
            ->leftJoin('l.bookCopy', 'bc')
            ->addSelect('bc')
            ->orderBy('rr.requestedAt', 'DESC')
            ->addOrderBy('rr.id', 'DESC');

        if (is_string($status) && trim($status) !== '') {
            $qb->andWhere('rr.status = :status')
                ->setParameter('status', strtoupper(trim($status)));
        }

        return $qb;
    }

    public function findPendingForLoanAndMember(Loan $loan, User $member): ?RenewalRequest
    {
        return $this->createQueryBuilder('rr')
            ->andWhere('rr.loan = :loan')
            ->andWhere('rr.member = :member')
            ->andWhere('rr.status = :status')
            ->setParameter('loan', $loan)
            ->setParameter('member', $member)
            ->setParameter('status', RenewalRequest::STATUS_PENDING)
            ->orderBy('rr.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return RenewalRequest[]
     */
    public function findRecentlyDecided(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('rr')
            ->leftJoin('rr.loan', 'l')
            ->addSelect('l')
            ->leftJoin('rr.member', 'm')
            ->addSelect('m')
            ->andWhere('rr.status IN (:decided)')
            ->andWhere('rr.requestedAt >= :since')
            ->setParameter('decided', [RenewalRequest::STATUS_APPROVED, RenewalRequest::STATUS_REJECTED])
            ->setParameter('since', $since)
            ->orderBy('rr.requestedAt', 'DESC')
            ->addOrderBy('rr.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findMemberLastEmailReminderSentAt(User $member): ?\DateTimeImmutable
    {
        $row = $this->createQueryBuilder('rr')
            ->select('rr.lastEmailReminderSentAt AS lastEmailReminderSentAt')
            ->andWhere('rr.member = :member')
            ->andWhere('rr.lastEmailReminderSentAt IS NOT NULL')
            ->setParameter('member', $member)
            ->orderBy('rr.lastEmailReminderSentAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $value = \is_array($row) ? ($row['lastEmailReminderSentAt'] ?? null) : null;

        return $value instanceof \DateTimeImmutable ? $value : null;
    }

    public function findMemberLastSmsReminderSentAt(User $member): ?\DateTimeImmutable
    {
        $row = $this->createQueryBuilder('rr')
            ->select('rr.lastSmsReminderSentAt AS lastSmsReminderSentAt')
            ->andWhere('rr.member = :member')
            ->andWhere('rr.lastSmsReminderSentAt IS NOT NULL')
            ->setParameter('member', $member)
            ->orderBy('rr.lastSmsReminderSentAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $value = \is_array($row) ? ($row['lastSmsReminderSentAt'] ?? null) : null;

        return $value instanceof \DateTimeImmutable ? $value : null;
    }
}
