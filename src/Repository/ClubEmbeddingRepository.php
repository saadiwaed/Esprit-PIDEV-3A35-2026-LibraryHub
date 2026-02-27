<?php

namespace App\Repository;

use App\Entity\ClubEmbedding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ClubEmbedding>
 */
class ClubEmbeddingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClubEmbedding::class);
    }

    /**
     * Trouve l'embedding d'un club
     */
    public function findByClubId(int $clubId): ?ClubEmbedding
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.club = :clubId')
            ->setParameter('clubId', $clubId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}