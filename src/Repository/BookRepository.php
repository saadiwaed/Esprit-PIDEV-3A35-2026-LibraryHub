<?php

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }



    public function search(array $filters)
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.category', 'c')
            ->leftJoin('b.author', 'a')
            ->addSelect('c','a');

        if (!empty($filters['q'])) {
            $qb->andWhere('b.title LIKE :q OR a.firstname LIKE :q OR a.lastname LIKE :q')
               ->setParameter('q', '%'.$filters['q'].'%');
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('c.id = :cat')
               ->setParameter('cat', $filters['category']);
        }

        if (!empty($filters['sort'])) {
            match ($filters['sort']) {
                'title' => $qb->orderBy('b.title','ASC'),
                'date'  => $qb->orderBy('b.createdAt','DESC'),
                default => $qb->orderBy('b.createdAt','DESC'),
            };
        }

        return $qb;
    }

    public function createFilteredQuery($q,$category,$author,$order)
    {
        $qb = $this->createQueryBuilder('b')  // FROM book b
            ->leftJoin('b.author','a')
            ->leftJoin('b.category','c')
            ->addSelect('a','c');
    
        if($q){
            $qb->andWhere('b.title LIKE :q OR a.firstname LIKE :q
        OR a.lastname LIKE :q ')
               ->setParameter('q','%'.$q.'%');
        }
    
        if($category){
            $qb->andWhere('c.id = :cat')
               ->setParameter('cat',$category);
        }
    
        if($author){
            $qb->andWhere('a.id = :aut')
               ->setParameter('aut',$author);
        }
    
        // ORDERING (ONLY HERE, NOT KNP)
        switch($order){
            case 'title':
                $qb->orderBy('b.title','ASC');
                break;
    
            default:
                $qb->orderBy('b.id','DESC');
        }
    
        return $qb->getQuery();
    }
    
// ===================== STATS =====================

// 1) Books per category (BAR CHART)
public function countBooksByCategory()
{
    return $this->createQueryBuilder('b')
        ->select('c.name as category, COUNT(b.id) as total')
        ->join('b.category', 'c')
        ->groupBy('c.id')
        ->orderBy('total', 'DESC')
        ->getQuery()
        ->getResult();
}

// 2) Books by status (PIE CHART)
public function countBooksByStatus()
{
    return $this->createQueryBuilder('b')
        ->select('b.status as status, COUNT(b.id) as total')
        ->groupBy('b.status')
        ->getQuery()
        ->getResult();
}

// 3) Books added per month (LINE CHART)
 // 3) Books added per month (LINE CHART)
public function findAllBooksForStats()
{
    return $this->createQueryBuilder('b')
        ->select('b.createdAt')
        ->getQuery()
        ->getResult();
}


    //    /**
    //     * @return Book[] Returns an array of Book objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Book
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
