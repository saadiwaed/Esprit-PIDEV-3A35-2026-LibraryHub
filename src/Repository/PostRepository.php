<?php

namespace App\Repository;

use App\Entity\Community;
use App\Entity\Post;
use App\Enum\PostStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * @return array<int, Post>
     */
    public function findForAdmin(?string $search = null, string $sort = 'newest'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.community', 'c')
            ->addSelect('c');

        $this->applySearch($qb, $search, true);
        $this->applySort($qb, $sort);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, Post>
     */
    public function findByCommunityForAdmin(Community $community, ?string $search = null, string $sort = 'newest'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.community = :community')
            ->setParameter('community', $community)
        ;

        $this->applySearch($qb, $search);
        $this->applySort($qb, $sort);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, Post>
     */
    public function findVisibleByCommunity(Community $community, ?string $search = null, string $sort = 'newest'): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.community = :community')
            ->andWhere('p.status = :status')
            ->setParameter('community', $community)
            ->setParameter('status', PostStatus::PUBLISHED)
        ;

        $this->applySearch($qb, $search);
        $this->applySort($qb, $sort);

        return $qb->getQuery()->getResult();
    }

    private function applySearch(QueryBuilder $qb, ?string $search, bool $includeCommunity = false): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $term = '%' . strtolower($search) . '%';

        $conditions = ['LOWER(p.title) LIKE :term', 'LOWER(p.content) LIKE :term'];
        if ($includeCommunity) {
            $conditions[] = 'LOWER(c.name) LIKE :term';
        }

        $qb
            ->andWhere('(' . implode(' OR ', $conditions) . ')')
            ->setParameter('term', $term);
    }

    private function applySort(QueryBuilder $qb, string $sort): void
    {
        $sort = strtolower(trim($sort));

        switch ($sort) {
            case 'oldest':
                $qb->orderBy('p.isPinned', 'DESC')
                    ->addOrderBy('p.createdAt', 'ASC');
                break;

            case 'most_commented':
                $qb->orderBy('p.isPinned', 'DESC')
                    ->addOrderBy('p.commentCount', 'DESC')
                    ->addOrderBy('p.createdAt', 'DESC');
                break;

            case 'title_asc':
                $qb->orderBy('p.isPinned', 'DESC')
                    ->addOrderBy('p.title', 'ASC')
                    ->addOrderBy('p.createdAt', 'DESC');
                break;

            case 'title_desc':
                $qb->orderBy('p.isPinned', 'DESC')
                    ->addOrderBy('p.title', 'DESC')
                    ->addOrderBy('p.createdAt', 'DESC');
                break;

            default:
                $qb->orderBy('p.isPinned', 'DESC')
                    ->addOrderBy('p.createdAt', 'DESC');
                break;
        }
    }
}
