<?php

namespace App\Repository;

use App\Entity\ReadingProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReadingProfile>
 */
class ReadingProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReadingProfile::class);
    }

    /**
     * @return list<ReadingProfile>
     */
    public function findForIndex(int $limit = 200): array
    {
        /** @var list<ReadingProfile> $profiles */
        $profiles = $this->createQueryBuilder('rp')
            ->leftJoin('rp.user', 'u')
            ->addSelect('u')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $profiles;
    }

    /**
     * @return list<ReadingProfile>
     */
    public function searchByUserNameOrEmail(string $query, int $limit = 50): array
    {
        /** @var list<ReadingProfile> $profiles */
        $profiles = $this->createQueryBuilder('rp')
            ->leftJoin('rp.user', 'u')
            ->addSelect('u')
            ->where('LOWER(u.firstName) LIKE LOWER(:query)')
            ->orWhere('LOWER(u.lastName) LIKE LOWER(:query)')
            ->orWhere('LOWER(u.email) LIKE LOWER(:query)')
            ->orWhere("LOWER(CONCAT(u.firstName, ' ', u.lastName)) LIKE LOWER(:query)")
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $profiles;
    }
}
