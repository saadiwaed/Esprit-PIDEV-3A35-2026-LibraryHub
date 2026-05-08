<?php

namespace App\Tests\Service\Forum;

use App\Entity\Community;
use App\Entity\Post;
use App\Service\Forum\PostManager;
use PHPUnit\Framework\TestCase;

final class PostManagerTest extends TestCase
{
    public function testValidPost(): void
    {
        $post = (new Post())
            ->setTitle('Discussion science-fiction')
            ->setContent('Voici un contenu valide avec assez de caracteres.')
            ->setCommunity(new Community());

        $manager = new PostManager();

        self::assertTrue($manager->validate($post));
    }

    public function testPostWithoutTitle(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre du post est obligatoire');

        $post = (new Post())
            ->setContent('Voici un contenu valide avec assez de caracteres.')
            ->setCommunity(new Community());

        $manager = new PostManager();
        $manager->validate($post);
    }

    public function testPostWithoutContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu du post est obligatoire');

        $post = (new Post())
            ->setTitle('Discussion science-fiction')
            ->setCommunity(new Community());

        $manager = new PostManager();
        $manager->validate($post);
    }

    public function testPostWithTooShortContent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu du post doit contenir au moins 10 caracteres');

        $post = (new Post())
            ->setTitle('Discussion science-fiction')
            ->setContent('court')
            ->setCommunity(new Community());

        $manager = new PostManager();
        $manager->validate($post);
    }

    public function testPostWithoutCommunity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La communaute est obligatoire');

        $post = (new Post())
            ->setTitle('Discussion science-fiction')
            ->setContent('Voici un contenu valide avec assez de caracteres.');

        $manager = new PostManager();
        $manager->validate($post);
    }
}
