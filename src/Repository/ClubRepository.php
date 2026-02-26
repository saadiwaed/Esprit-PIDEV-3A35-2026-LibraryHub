<?php

namespace App\Repository;

use App\Entity\Club;
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

    public function findByFilters(
        string $search = '',
        string $status = '',
        string $category = '',
        string $sort = 'createdDate',
        string $order = 'desc'
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
        $sort = in_array($sort, $validSortFields) ? $sort : 'createdDate';
        $order = strtolower($order) === 'asc' ? 'asc' : 'desc';
        
        $qb->orderBy('c.' . $sort, $order);
        
        return $qb->getQuery()->getResult();
    }
    
    public function countByStatus(): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('c.status, COUNT(c.id) as count')
            ->groupBy('c.status')
            ->getQuery()
            ->getResult();
        
        $stats = [];
        foreach ($results as $result) {
            $stats[$result['status']->value] = $result['count'];
        }
        
        return $stats;
    }
    
    public function findAllCategories(): array
    {
        $results = $this->createQueryBuilder('c')
            ->select('DISTINCT c.category')
            ->orderBy('c.category', 'ASC')
            ->getQuery()
            ->getResult();
        
        return array_column($results, 'category');
    }
}
