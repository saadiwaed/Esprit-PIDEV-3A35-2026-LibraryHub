<?php

namespace App\Repository;

use App\Entity\Community;
use App\Entity\Post;
use App\Enum\PostStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
    public function findByCommunityForAdmin(Community $community): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.community = :community')
            ->setParameter('community', $community)
            ->orderBy('p.isPinned', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, Post>
     */
    public function findVisibleByCommunity(Community $community): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.community = :community')
            ->andWhere('p.status = :status')
            ->setParameter('community', $community)
            ->setParameter('status', PostStatus::PUBLISHED)
            ->orderBy('p.isPinned', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
