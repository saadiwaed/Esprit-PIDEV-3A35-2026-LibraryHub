<?php

namespace App\Repository;

use App\Entity\ChallengeParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Legacy repository kept for compatibility.
 *
 * @extends ServiceEntityRepository<ChallengeParticipant>
 */
class ReadingChallengeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChallengeParticipant::class);
    }

    /**
     * @return list<ChallengeParticipant>
     */
    public function findForIndex(int $limit = 200): array
    {
        /** @var list<ChallengeParticipant> $items */
        $items = $this->createQueryBuilder('cp')
            ->leftJoin('cp.participant', 'p')
            ->addSelect('p')
            ->orderBy('cp.joinedAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $items;
    }
}
