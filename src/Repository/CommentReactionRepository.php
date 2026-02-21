<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\CommentReaction;
use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommentReaction>
 */
class CommentReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommentReaction::class);
    }

    public function findOneByCommentAndUser(Comment $comment, User $user): ?CommentReaction
    {
        return $this->findOneBy([
            'comment' => $comment,
            'user' => $user,
        ]);
    }

    /**
     * @return array<int, CommentReaction>
     */
    public function findByPostAndUser(Post $post, User $user): array
    {
        return $this->createQueryBuilder('cr')
            ->innerJoin('cr.comment', 'c')
            ->andWhere('c.post = :post')
            ->andWhere('cr.user = :user')
            ->setParameter('post', $post)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }
}
