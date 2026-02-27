<?php

namespace App\Repository;

use App\Entity\LoanRequest;
use App\Enum\LoanRequestStatus;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoanRequest>
 */
class LoanRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoanRequest::class);
    }

    /**
     * @return LoanRequest[]
     */
    public function findPending(int $limit = 50): array
    {
        return $this->createQueryBuilder('lr')
            ->andWhere('lr.status = :status')
            ->setParameter('status', LoanRequestStatus::PENDING)
            ->orderBy('lr.requestedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return LoanRequest[]
     */
    public function findLatestForMember(User $member, int $limit = 5): array
    {
        return $this->createQueryBuilder('lr')
            ->andWhere('lr.member = :member')
            ->setParameter('member', $member)
            ->orderBy('lr.requestedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

