<?php

namespace App\Repository;

use App\Entity\EventRegistration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use App\Enum\RegistrationStatus;
use App\Entity\Event;
/**
 * @extends ServiceEntityRepository<EventRegistrationPhp>
 */
class EventRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventRegistration::class);
    }

public function findUserRegistrations(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', RegistrationStatus::CONFIRMED) // ✅ Objet, PAS string
            ->orderBy('r.registeredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findUserRegistrationForEvent(Event $event, User $user): ?EventRegistration
    {
        return $this->createQueryBuilder('r')
            ->where('r.event = :event')
            ->andWhere('r.user = :user')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countConfirmedRegistrations(Event $event): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.event = :event')
            ->andWhere('r.status = :status')
            ->setParameter('event', $event)
            ->setParameter('status', RegistrationStatus::CONFIRMED) // ✅ Objet, PAS string
            ->getQuery()
            ->getSingleScalarResult();
    }
    // ✅ HISTORIQUE COMPLET (TOUS LES STATUTS)
    public function findUserHistory(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.registeredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ✅ UNIQUEMENT LES ANNULATIONS
    public function findUserCancellations(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', RegistrationStatus::CANCELLED->value)
            ->orderBy('r.registeredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ✅ STATISTIQUES RAPIDES
    public function getUserStats(User $user): array
    {
        $total = $this->count(['user' => $user]);
        
        $confirmed = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', RegistrationStatus::CONFIRMED->value)
            ->getQuery()
            ->getSingleScalarResult();
            
        $cancelled = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', RegistrationStatus::CANCELLED->value)
            ->getQuery()
            ->getSingleScalarResult();
            
        return [
            'total' => (int) $total,
            'confirmed' => (int) $confirmed,
            'cancelled' => (int) $cancelled,
            'active' => (int) $confirmed, // alias
        ];
    }
}
