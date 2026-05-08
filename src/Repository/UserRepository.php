<?php

namespace App\Repository;

use App\Entity\Loan;
use App\Entity\User;
use App\Enum\LoanStatus;
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

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * @return list<User>
     */
    public function findForIndex(int $limit = 200): array
    {
        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->leftJoin('u.roles', 'r')
            ->addSelect('r')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $users;
    }

    /**
     * @return list<User>
     */
    public function findByRole(string $role): array
    {
        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->leftJoin('u.roles', 'r')
            ->andWhere('r.name = :role')
            ->setParameter('role', $role)
            ->getQuery()
            ->getResult();

        return $users;
    }

    /**
     * @return list<User>
     */
    public function findActiveUsers(): array
    {
        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->andWhere('u.status = :status')
            ->setParameter('status', 'ACTIVE')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();

        return $users;
    }

    /**
     * @return list<User>
     */
    public function findPremiumMembers(): array
    {
        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->andWhere('u.isPremium = :premium')
            ->setParameter('premium', true)
            ->andWhere('u.status = :status')
            ->setParameter('status', 'ACTIVE')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();

        return $users;
    }

    /**
     * @return list<User>
     */
    public function findUsersWithOverdueLoans(): array
    {
        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->innerJoin(Loan::class, 'l', 'WITH', 'l.member = u')
            ->andWhere('l.status = :status')
            ->setParameter('status', LoanStatus::OVERDUE)
            ->groupBy('u.id')
            ->getQuery()
            ->getResult();

        return $users;
    }

    /**
     * @return list<User>
     */
    public function searchUsers(string $query): array
    {
        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->where('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->orWhere('u.email LIKE :query')
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();

        return $users;
    }

    /**
     * @return list<User>
     */
    public function searchByNameOrEmail(string $query, int $limit = 10): array
    {
        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->where('LOWER(u.firstName) LIKE LOWER(:query)')
            ->orWhere('LOWER(u.lastName) LIKE LOWER(:query)')
            ->orWhere('LOWER(u.email) LIKE LOWER(:query)')
            ->orWhere("LOWER(CONCAT(u.firstName, ' ', u.lastName)) LIKE LOWER(:query)")
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('u.firstName', 'ASC')
            ->addOrderBy('u.lastName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $users;
    }
}
