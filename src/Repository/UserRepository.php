<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    // Methodes personnalisees
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->getQuery()
            ->getResult();
    }

    public function findActiveUsers(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findPremiumMembers(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.membershipType = :type')
            ->setParameter('type', 'premium')
            ->andWhere('u.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getResult();
    }

    public function findUsersWithOverdueLoans(): array
    {
        return $this->createQueryBuilder('u')
            ->leftJoin('u.loans', 'l')
            ->andWhere('l.status = :status')
            ->setParameter('status', 'overdue')
            ->getQuery()
            ->getResult();
    }

    public function searchUsers(string $query): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->orWhere('u.email LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search users by name or email with limit for suggestions
     */
    public function searchByNameOrEmail(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.firstName) LIKE LOWER(:query)')
            ->orWhere('LOWER(u.lastName) LIKE LOWER(:query)')
            ->orWhere('LOWER(u.email) LIKE LOWER(:query)')
            ->orWhere("LOWER(CONCAT(u.firstName, ' ', u.lastName)) LIKE LOWER(:query)")
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
