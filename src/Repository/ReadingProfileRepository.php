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

    //    /**
    //     * @return ReadingProfile[] Returns an array of ReadingProfile objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?ReadingProfile
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Search reading profiles by user name or email
     */
    public function searchByUserNameOrEmail(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('rp')
            ->join('rp.user', 'u')
            ->where('LOWER(u.firstName) LIKE LOWER(:query)')
            ->orWhere('LOWER(u.lastName) LIKE LOWER(:query)')
            ->orWhere('LOWER(u.email) LIKE LOWER(:query)')
            ->orWhere("LOWER(CONCAT(u.firstName, ' ', u.lastName)) LIKE LOWER(:query)")
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
