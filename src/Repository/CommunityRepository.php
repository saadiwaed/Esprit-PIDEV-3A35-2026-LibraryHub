<?php

namespace App\Repository;

use App\Entity\Community;
use App\Enum\CommunityStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Community>
 */
class CommunityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Community::class);
    }

    /**
     * @return array<int, Community>
     */
    public function findForAdmin(?string $search = null, string $sort = 'newest'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'creator')
            ->addSelect('creator');

        $this->applySearch($qb, $search);
        $this->applySort($qb, $sort);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, Community>
     */
    public function findPublicApproved(): array
    {
        return $this->findPublicApprovedByFilters();
    }

    /**
     * @return array<int, Community>
     */
    public function findPublicApprovedByFilters(?string $search = null, string $sort = 'newest'): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.createdBy', 'creator')
            ->addSelect('creator')
            ->andWhere('c.isPublic = :isPublic')
            ->andWhere('c.status = :status')
            ->setParameter('isPublic', true)
            ->setParameter('status', CommunityStatus::APPROVED)
        ;

        $this->applySearch($qb, $search);
        $this->applySort($qb, $sort);

        return $qb->getQuery()->getResult();
    }

    private function applySearch(QueryBuilder $qb, ?string $search): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $term = '%' . strtolower($search) . '%';

        $qb
            ->andWhere('(LOWER(c.name) LIKE :term OR LOWER(c.description) LIKE :term OR LOWER(c.purpose) LIKE :term)')
            ->setParameter('term', $term);
    }

    private function applySort(QueryBuilder $qb, string $sort): void
    {
        $sort = strtolower(trim($sort));

        switch ($sort) {
            case 'oldest':
                $qb->orderBy('c.createdAt', 'ASC');
                break;

            case 'name_asc':
                $qb->orderBy('c.name', 'ASC');
                break;

            case 'name_desc':
                $qb->orderBy('c.name', 'DESC');
                break;

            case 'most_posts':
                $qb->orderBy('c.postCount', 'DESC')
                    ->addOrderBy('c.createdAt', 'DESC');
                break;

            case 'most_members':
                $qb->orderBy('c.memberCount', 'DESC')
                    ->addOrderBy('c.createdAt', 'DESC');
                break;

            default:
                $qb->orderBy('c.createdAt', 'DESC');
                break;
        }
    }
}
