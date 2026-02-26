<?php

namespace App\Repository;

use App\Entity\LoanRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanRequest>
 */
final class LoanRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanRequest::class);
    }

    /**
     * @return LoanRequest[]
     */
    public function findRecentlyDecided(\DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:decided)')
            ->andWhere('r.requestedAt >= :since')
            ->setParameter('decided', [LoanRequest::STATUS_APPROVED, LoanRequest::STATUS_REJECTED])
            ->setParameter('since', $since)
            ->orderBy('r.requestedAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findMemberLastEmailReminderSentAt(User $member): ?\DateTimeImmutable
    {
        $row = $this->createQueryBuilder('r')
            ->select('r.lastEmailReminderSentAt AS lastEmailReminderSentAt')
            ->andWhere('r.member = :member')
            ->andWhere('r.lastEmailReminderSentAt IS NOT NULL')
            ->setParameter('member', $member)
            ->orderBy('r.lastEmailReminderSentAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $value = \is_array($row) ? ($row['lastEmailReminderSentAt'] ?? null) : null;

        return $value instanceof \DateTimeImmutable ? $value : null;
    }

    public function findMemberLastSmsReminderSentAt(User $member): ?\DateTimeImmutable
    {
        $row = $this->createQueryBuilder('r')
            ->select('r.lastSmsReminderSentAt AS lastSmsReminderSentAt')
            ->andWhere('r.member = :member')
            ->andWhere('r.lastSmsReminderSentAt IS NOT NULL')
            ->setParameter('member', $member)
            ->orderBy('r.lastSmsReminderSentAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $value = \is_array($row) ? ($row['lastSmsReminderSentAt'] ?? null) : null;

        return $value instanceof \DateTimeImmutable ? $value : null;
    }
}
