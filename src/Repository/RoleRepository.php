<?php

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function save(Role $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Role $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find a role by its name
     */
    public function findByName(string $name): ?Role
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all roles ordered by name
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search roles by name (for autocomplete)
     */
    public function searchByName(string $query, int $limit = 50): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('r.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find roles with filters (search, hasUsers)
     */
    public function findWithFilters(?string $search = null, ?string $hasUsers = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.users', 'u')
            ->groupBy('r.id');

        // Apply search filter
        if ($search) {
            $qb->andWhere('r.name LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Apply hasUsers filter
        if ($hasUsers === 'with') {
            $qb->having('COUNT(u.id) > 0');
        } elseif ($hasUsers === 'without') {
            $qb->having('COUNT(u.id) = 0');
        }

        return $qb->orderBy('r.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }
}
