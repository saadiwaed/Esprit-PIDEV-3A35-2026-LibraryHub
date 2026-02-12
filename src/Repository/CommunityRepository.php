<?php

namespace App\Repository;

use App\Entity\Community;
use App\Enum\CommunityStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Community>
 */
class CommunityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Community::class);
    }

    /**
     * @return array<int, Community>
     */
    public function findPublicApproved(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isPublic = :isPublic')
            ->andWhere('c.status = :status')
            ->setParameter('isPublic', true)
            ->setParameter('status', CommunityStatus::APPROVED)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
