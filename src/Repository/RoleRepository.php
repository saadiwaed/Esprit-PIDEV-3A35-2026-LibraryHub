<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    //    /**
    //     * @return Role[] Returns an array of Role objects
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

    //    public function findOneBySomeField($value): ?Role
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    public function searchByNameOrDescription(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->where('LOWER(r.name) LIKE LOWER(:q)')
            ->orWhere('LOWER(r.description) LIKE LOWER(:q)')
            ->setParameter('q', '%' . $query . '%')
            ->orderBy('r.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getQueryBuilderForList(?string $search = null): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.name', 'ASC');

        if ($search !== null && $search !== '') {
            $qb
                ->where('LOWER(r.name) LIKE LOWER(:search)')
                ->orWhere('LOWER(r.description) LIKE LOWER(:search)')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb;
    }
}
