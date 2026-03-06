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

//    /**
//     * @return ReadingProfile[] Returns an array of ReadingProfile objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('r.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?ReadingProfile
//    {
//        return $this->createQueryBuilder('r')
//            ->andWhere('r.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }

    public function searchByUserNameOrEmail(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('rp')
            ->innerJoin('rp.user', 'u')
            ->where('LOWER(u.firstName) LIKE LOWER(:q)')
            ->orWhere('LOWER(u.lastName) LIKE LOWER(:q)')
            ->orWhere('LOWER(u.email) LIKE LOWER(:q)')
            ->orWhere("LOWER(CONCAT(u.firstName, ' ', u.lastName)) LIKE LOWER(:q)")
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getQueryBuilderForList(?string $search = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('rp')
            ->innerJoin('rp.user', 'u')
            ->addSelect('u')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC');

        if ($search !== null && $search !== '') {
            $qb
                ->andWhere(
                    'LOWER(u.firstName) LIKE LOWER(:search) OR LOWER(u.lastName) LIKE LOWER(:search) OR LOWER(u.email) LIKE LOWER(:search) OR LOWER(CONCAT(u.firstName, \' \', u.lastName)) LIKE LOWER(:search)'
                )
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb;
    }
}