<?php

namespace App\Repository;

use App\Entity\Author;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Author::class);
    }
// ================= AUTHOR STATS =================

// Top authors by number of books
public function countBooksByAuthor()
{
    return $this->createQueryBuilder('a')
        ->select("CONCAT(a.firstname, ' ', a.lastname) as author, COUNT(b.id) as total")
        ->leftJoin('a.books', 'b')
        ->groupBy('a.id')
        ->orderBy('total', 'DESC')
        ->getQuery()
        ->getResult();
}

// Authors nationality distribution
public function countAuthorsByNationality()
{
    return $this->createQueryBuilder('a')
        ->select('a.nationality as nationality, COUNT(a.id) as total')
        ->where('a.nationality IS NOT NULL')
        ->groupBy('a.nationality')
        ->orderBy('total', 'DESC')
        ->getQuery()
        ->getResult();
}

    //    /**
    //     * @return Author[] Returns an array of Author objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Author
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
