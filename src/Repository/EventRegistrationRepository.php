<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\EventRegistration;
use App\Entity\User;
use App\Enum\RegistrationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventRegistration>
 */
class EventRegistrationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventRegistration::class);
    }

    /**
     * @return list<EventRegistration>
     */
    public function findUserRegistrations(User $user): array
    {
        /** @var list<EventRegistration> $result */
        $result = $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', RegistrationStatus::CONFIRMED)
            ->orderBy('r.registeredAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
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
            ->setParameter('status', RegistrationStatus::CONFIRMED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<EventRegistration>
     */
    public function findUserHistory(User $user): array
    {
        /** @var list<EventRegistration> $result */
        $result = $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.registeredAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return list<EventRegistration>
     */
    public function findUserCancellations(User $user): array
    {
        /** @var list<EventRegistration> $result */
        $result = $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', RegistrationStatus::CANCELLED)
            ->orderBy('r.registeredAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * @return array{total: int, confirmed: int, cancelled: int, active: int}
     */
    public function getUserStats(User $user): array
    {
        $total = $this->count(['user' => $user]);

        $confirmed = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', RegistrationStatus::CONFIRMED)
            ->getQuery()
            ->getSingleScalarResult();

        $cancelled = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', RegistrationStatus::CANCELLED)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => (int) $total,
            'confirmed' => (int) $confirmed,
            'cancelled' => (int) $cancelled,
            'active' => (int) $confirmed,
        ];
    }

    public function findFirstWaitlisted(Event $event): ?EventRegistration
    {
        return $this->createQueryBuilder('r')
            ->where('r.event = :event')
            ->andWhere('r.status = :status')
            ->setParameter('event', $event)
            ->setParameter('status', RegistrationStatus::WAITLISTED)
            ->orderBy('r.registeredAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
