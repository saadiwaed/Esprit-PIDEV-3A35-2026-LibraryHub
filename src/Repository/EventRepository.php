<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use App\Enum\EventStatus;
use App\Enum\RegistrationStatus;
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

    /**
     * @return list<Event>
     */
    public function findByFilters(
        string $search = '',
        string $status = '',
        string $sort = 'startDateTime',
        string $order = 'asc'
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.organizingClubs', 'c');

        if ($search !== '') {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('e.title', ':search'),
                    $qb->expr()->like('e.description', ':search'),
                    $qb->expr()->like('e.location', ':search'),
                    $qb->expr()->like('c.title', ':search')
                )
            )
                ->setParameter('search', '%'.$search.'%');
        }

        if ($status !== '') {
            $qb->andWhere('e.status = :status')
                ->setParameter('status', $status);
        }

        $validSortFields = ['title', 'startDateTime', 'createdDate', 'capacity'];
        $sort = in_array($sort, $validSortFields, true) ? $sort : 'startDateTime';
        $order = strtolower($order) === 'desc' ? 'desc' : 'asc';

        $qb->orderBy('e.'.$sort, $order);

        /** @var list<Event> $events */
        $events = $qb->getQuery()->getResult();

        return $events;
    }

    /**
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        /** @var list<array{status: EventStatus|string, count: string}> $results */
        $results = $this->createQueryBuilder('e')
            ->select('e.status, COUNT(e.id) as count')
            ->groupBy('e.status')
            ->getQuery()
            ->getResult();

        $stats = [];
        foreach ($results as $result) {
            $status = $result['status'] instanceof EventStatus
                ? $result['status']->value
                : (string) $result['status'];

            $stats[$status] = (int) $result['count'];
        }

        return $stats;
    }

    /**
     * @return list<Event>
     */
    public function findByUser(
        User $user,
        string $search = '',
        ?string $status = null,
        string $sort = 'startDateTime',
        string $order = 'asc'
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->where('e.createdBy = :user')
            ->setParameter('user', $user);

        if ($search !== '') {
            $qb->andWhere('e.title LIKE :search OR e.description LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('e.status = :status')
                ->setParameter('status', $status);
        }

        $validSortFields = ['title', 'startDateTime', 'createdDate', 'capacity'];
        $safeSort = in_array($sort, $validSortFields, true) ? $sort : 'startDateTime';
        $safeOrder = strtolower($order) === 'desc' ? 'desc' : 'asc';
        $qb->orderBy('e.'.$safeSort, $safeOrder);

        /** @var list<Event> $events */
        $events = $qb->getQuery()->getResult();

        return $events;
    }

    /**
     * @return array<string, int>
     */
    public function countByStatusForUser(User $user): array
    {
        /** @var list<array{status: EventStatus|string, count: string}> $results */
        $results = $this->createQueryBuilder('e')
            ->select('e.status, COUNT(e.id) as count')
            ->where('e.createdBy = :user')
            ->setParameter('user', $user)
            ->groupBy('e.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach (EventStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($results as $result) {
            $statusValue = $result['status'] instanceof EventStatus
                ? $result['status']->value
                : (string) $result['status'];
            $counts[$statusValue] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * @return list<Event>
     */
    public function findDiscoverEvents(
        User $user,
        string $search = '',
        ?string $status = null,
        string $sort = 'startDateTime',
        string $order = 'asc'
    ): array {
        $qb = $this->createQueryBuilder('e')
            ->where('e.createdBy != :user')
            ->andWhere('e.registrationDeadline > :now')
            ->andWhere('e.startDateTime > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable());

        if ($search !== '') {
            $qb->andWhere('e.title LIKE :search OR e.description LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        if ($status !== null && $status !== '') {
            $qb->andWhere('e.status = :status')
                ->setParameter('status', $status);
        }

        $validSortFields = ['title', 'startDateTime', 'createdDate', 'capacity'];
        $safeSort = in_array($sort, $validSortFields, true) ? $sort : 'startDateTime';
        $safeOrder = strtolower($order) === 'desc' ? 'desc' : 'asc';
        $qb->orderBy('e.'.$safeSort, $safeOrder);

        /** @var list<Event> $events */
        $events = $qb->getQuery()->getResult();

        return $events;
    }

    public function isUserRegistered(Event $event, User $user): bool
    {
        $count = (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from('App\Entity\EventRegistration', 'r')
            ->where('r.event = :event')
            ->andWhere('r.user = :user')
            ->andWhere('r.status = :status')
            ->setParameter('event', $event)
            ->setParameter('user', $user)
            ->setParameter('status', RegistrationStatus::CONFIRMED)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
