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

    public function save(ReadingProfile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ReadingProfile $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find reading profile by user ID
     */
    public function findByUserId(int $userId): ?ReadingProfile
    {
        return $this->createQueryBuilder('rp')
            ->andWhere('rp.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get top readers (ordered by total books read)
     */
    public function findTopReaders(int $limit = 10): array
    {
        return $this->createQueryBuilder('rp')
            ->orderBy('rp.totalBooksRead', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search reading profiles by user name or email (for autocomplete)
     */
    public function searchByUserNameOrEmail(string $query, int $limit = 50): array
    {
        return $this->createQueryBuilder('rp')
            ->innerJoin('rp.user', 'u')
            ->where('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->orWhere('u.email LIKE :query')
            ->orWhere('CONCAT(u.firstName, \' \', u.lastName) LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.lastName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find reading profiles with filters (search, booksRead range, hasGoal)
     */
    public function findWithFilters(?string $search = null, ?string $booksRead = null, ?string $hasGoal = null): array
    {
        $qb = $this->createQueryBuilder('rp')
            ->innerJoin('rp.user', 'u');

        // Apply search filter
        if ($search) {
            $qb->andWhere('u.firstName LIKE :search OR u.lastName LIKE :search OR u.email LIKE :search OR CONCAT(u.firstName, \' \', u.lastName) LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Apply books read range filter
        if ($booksRead === '0-10') {
            $qb->andWhere('rp.totalBooksRead >= 0 AND rp.totalBooksRead <= 10');
        } elseif ($booksRead === '11-50') {
            $qb->andWhere('rp.totalBooksRead >= 11 AND rp.totalBooksRead <= 50');
        } elseif ($booksRead === '50+') {
            $qb->andWhere('rp.totalBooksRead > 50');
        }

        // Apply hasGoal filter
        if ($hasGoal === 'with') {
            $qb->andWhere('rp.readingGoalPerMonth IS NOT NULL AND rp.readingGoalPerMonth > 0');
        } elseif ($hasGoal === 'without') {
            $qb->andWhere('rp.readingGoalPerMonth IS NULL OR rp.readingGoalPerMonth = 0');
        }

        return $qb->orderBy('u.lastName', 'ASC')
                  ->getQuery()
                  ->getResult();
    }
}
