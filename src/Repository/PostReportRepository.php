<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\PostReport;
use App\Entity\User;
use App\Enum\PostReportStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostReport>
 */
class PostReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostReport::class);
    }

    public function findOneByPostAndReporter(Post $post, User $reporter): ?PostReport
    {
        return $this->findOneBy([
            'post' => $post,
            'reporter' => $reporter,
        ]);
    }

    public function findPendingOneByPostAndReporter(Post $post, User $reporter): ?PostReport
    {
        return $this->findOneBy([
            'post' => $post,
            'reporter' => $reporter,
            'status' => PostReportStatus::PENDING,
        ]);
    }

    /**
     * @return array<int, PostReport>
     */
    public function findPendingByPost(Post $post): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.reporter', 'reporter')
            ->addSelect('reporter')
            ->andWhere('r.post = :post')
            ->andWhere('r.status = :status')
            ->setParameter('post', $post)
            ->setParameter('status', PostReportStatus::PENDING)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, PostReport>
     */
    public function findPendingForModerationQueue(): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.post', 'p')
            ->addSelect('p')
            ->leftJoin('p.community', 'c')
            ->addSelect('c')
            ->leftJoin('p.createdBy', 'postAuthor')
            ->addSelect('postAuthor')
            ->leftJoin('r.reporter', 'reporter')
            ->addSelect('reporter')
            ->andWhere('r.status = :status')
            ->setParameter('status', PostReportStatus::PENDING)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
