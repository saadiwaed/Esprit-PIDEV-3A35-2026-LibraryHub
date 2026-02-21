<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\PostReaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostReaction>
 */
class PostReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostReaction::class);
    }

    public function findOneByPostAndUser(Post $post, User $user): ?PostReaction
    {
        return $this->findOneBy([
            'post' => $post,
            'user' => $user,
        ]);
    }
}
