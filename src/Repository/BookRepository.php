<?php

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * @param array<string, mixed> $filters
     */
    public function search(array $filters): QueryBuilder
    {
        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.category', 'c')
            ->leftJoin('b.author', 'a')
            ->addSelect('c', 'a');

        if (!empty($filters['q'])) {
            $qb->andWhere('b.title LIKE :q OR a.firstname LIKE :q OR a.lastname LIKE :q')
                ->setParameter('q', '%' . $filters['q'] . '%');
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('c.id = :cat')
                ->setParameter('cat', $filters['category']);
        }

        if (!empty($filters['sort'])) {
            match ($filters['sort']) {
                'title' => $qb->orderBy('b.title', 'ASC'),
                'date'  => $qb->orderBy('b.createdAt', 'DESC'),
                default => $qb->orderBy('b.createdAt', 'DESC'),
            };
        }

        return $qb;
    }

    public function createFilteredQuery(
        ?string $q,
        ?int $category,
        ?int $author,
        ?string $order
    ): QueryBuilder {

        $qb = $this->createQueryBuilder('b')
            ->leftJoin('b.author', 'a')
            ->leftJoin('b.category', 'c')
            ->addSelect('a', 'c');

        if ($q) {
            $qb->andWhere('b.title LIKE :q OR a.firstname LIKE :q OR a.lastname LIKE :q')
                ->setParameter('q', '%' . $q . '%');
        }

        if ($category) {
            $qb->andWhere('c.id = :cat')
                ->setParameter('cat', $category);
        }

        if ($author) {
            $qb->andWhere('a.id = :aut')
                ->setParameter('aut', $author);
        }

        switch ($order) {
            case 'title':
                $qb->orderBy('b.title', 'ASC');
                break;

            default:
                $qb->orderBy('b.id', 'DESC');
        }

        return $qb;
    }

    // ===================== STATS =====================

    /**
     * @return array<int, array{category:string,total:string}>
     */
    public function countBooksByCategory(): array
    {
        return $this->createQueryBuilder('b')
            ->select('c.name as category, COUNT(b.id) as total')
            ->join('b.category', 'c')
            ->groupBy('c.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array{status:string,total:string}>
     */
    public function countBooksByStatus(): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.status as status, COUNT(b.id) as total')
            ->groupBy('b.status')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array{createdAt:\DateTimeInterface}>
     */
    public function findAllBooksForStats(): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.createdAt')
            ->getQuery()
            ->getResult();
    }
}