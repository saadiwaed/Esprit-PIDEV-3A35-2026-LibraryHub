<?php

namespace App\Repository;

use App\Entity\Club;
use App\Enum\ClubStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Club>
 */
class ClubRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Club::class);
    }

    /**
     * @return list<Club>
     */
    public function findByFilters(
        string $search = '',
        string $status = '',
        string $category = '',
        string $sort = 'createdDate',
        string $order = 'desc',
        int $limit = 200
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.organizedEvents', 'e');
        
        // Recherche
        if (!empty($search)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('c.title', ':search'),
                    $qb->expr()->like('c.description', ':search'),
                    $qb->expr()->like('c.category', ':search'),
                    $qb->expr()->like('c.meetingLocation', ':search')
                )
            )
            ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtre par statut
        if (!empty($status)) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $status);
        }
        
        // Filtre par catégorie
        if (!empty($category)) {
            $qb->andWhere('c.category = :category')
               ->setParameter('category', $category);
        }
        
        // Tri
        $validSortFields = ['title', 'createdDate', 'meetingDate', 'capacity'];
        $sort = in_array($sort, $validSortFields, true) ? $sort : 'createdDate';
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';
        
        $qb->orderBy('c.' . $sort, $order)
            ->setMaxResults(max(1, $limit));
        
        /** @var list<Club> $clubs */
        $clubs = $qb->getQuery()->getResult();

        return $clubs;
    }
    
    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        /** @var list<array{status: ClubStatus|string, count: string}> $results */
        $results = $this->createQueryBuilder('c')
            ->select('c.status, COUNT(c.id) as count')
            ->groupBy('c.status')
            ->getQuery()
            ->getResult();
        
        $stats = [];
        foreach ($results as $result) {
            $status = $result['status'] instanceof ClubStatus ? $result['status']->value : (string) $result['status'];
            $stats[$status] = (int) $result['count'];
        }
        
        return $stats;
    }
    
    /**
     * @return list<string>
     */
    public function findAllCategories(): array
    {
        /** @var list<array{category: string|null}> $results */
        $results = $this->createQueryBuilder('c')
            ->select('DISTINCT c.category')
            ->orderBy('c.category', 'ASC')
            ->getQuery()
            ->getResult();
        
        $categories = array_values(array_filter(array_column($results, 'category'), static fn ($value): bool => is_string($value) && $value !== ''));

        /** @var list<string> $categories */
        return $categories;
    }

}
