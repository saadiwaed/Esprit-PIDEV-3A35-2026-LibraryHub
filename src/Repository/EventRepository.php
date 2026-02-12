<?php

namespace App\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findByFilters(
        string $search = '',
        string $status = '',
        string $sort = 'startDateTime',
        string $order = 'asc'
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.organizingClubs', 'c');
        
        // Recherche
        if (!empty($search)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('e.title', ':search'),
                    $qb->expr()->like('e.description', ':search'),
                    $qb->expr()->like('e.location', ':search'),
                    $qb->expr()->like('c.title', ':search')
                )
            )
            ->setParameter('search', '%' . $search . '%');
        }
        
        // Filtre par statut
        if (!empty($status)) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $status);
        }
        
        // Tri
        $validSortFields = ['title', 'startDateTime', 'createdDate', 'capacity'];
        $sort = in_array($sort, $validSortFields) ? $sort : 'startDateTime';
        $order = strtolower($order) === 'desc' ? 'desc' : 'asc';
        
        $qb->orderBy('e.' . $sort, $order);
        
        return $qb->getQuery()->getResult();
    }
    
    public function countByStatus(): array
    {
        $results = $this->createQueryBuilder('e')
            ->select('e.status, COUNT(e.id) as count')
            ->groupBy('e.status')
            ->getQuery()
            ->getResult();
        
        $stats = [];
        foreach ($results as $result) {
            $stats[$result['status']->value] = $result['count'];
        }
        
        return $stats;
    }
}
