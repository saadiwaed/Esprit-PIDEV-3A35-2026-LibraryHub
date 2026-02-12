<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventStatus;
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
    
    /**
     * Trouve les événements créés par l'utilisateur connecté
     */
    public function findByUser(User $user, string $search = '', ?string $status = null, string $sort = 'startDateTime', string $order = 'asc'): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.createdBy = :user')
            ->setParameter('user', $user);
        
        if ($search) {
            $qb->andWhere('e.title LIKE :search OR e.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }
        
        if ($status) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $status);
        }
        
        $qb->orderBy('e.' . $sort, $order);
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les événements par statut pour l'utilisateur connecté
     */
    public function countByStatusForUser(User $user): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.status, COUNT(e.id) as count')
            ->where('e.createdBy = :user')
            ->setParameter('user', $user)
            ->groupBy('e.status');
        
        $results = $qb->getQuery()->getResult();
        
        $counts = [];
        foreach (EventStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }
        
        foreach ($results as $result) {
            $status = $result['status'] instanceof EventStatus 
                ? $result['status']->value 
                : $result['status'];
            $counts[$status] = (int)$result['count'];
        }
        
        return $counts;
    }
    // src/Repository/EventRepository.php

/**
 * Trouve tous les événements disponibles pour inscription
 * (sauf ceux créés par l'utilisateur, avec places disponibles, date limite non dépassée)
 */
public function findDiscoverEvents(User $user, string $search = '', ?string $status = null, string $sort = 'startDateTime', string $order = 'asc'): array
{
    $qb = $this->createQueryBuilder('e')
        ->where('e.createdBy != :user')
        ->andWhere('e.registrationDeadline > :now')
        ->andWhere('e.startDateTime > :now')
        ->setParameter('user', $user)
        ->setParameter('now', new \DateTime());
    
    if ($search) {
        $qb->andWhere('e.title LIKE :search OR e.description LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }
    
    if ($status) {
        $qb->andWhere('e.status = :status')
           ->setParameter('status', $status);
    }
    
    $qb->orderBy('e.' . $sort, $order);
    
    return $qb->getQuery()->getResult();
}

/**
 * Vérifie si l'utilisateur est inscrit à l'événement
 */
public function isUserRegistered(Event $event, User $user): bool
{
    $qb = $this->getEntityManager()->createQueryBuilder()
        ->select('COUNT(r.id)')
        ->from('App\Entity\EventRegistration', 'r')
        ->where('r.event = :event')
        ->andWhere('r.user = :user')
        ->andWhere('r.status = :status')
        ->setParameter('event', $event)
        ->setParameter('user', $user)
        ->setParameter('status', \App\Enum\RegistrationStatus::CONFIRMED);
    
    return (bool) $qb->getQuery()->getSingleScalarResult();
}
}