<?php

namespace App\Service\Forum;

use App\Entity\Post;

final class PostManager
{
    public function validate(Post $post): bool
    {
        $title = trim((string) $post->getTitle());
        if ($title === '') {
            throw new \InvalidArgumentException('Le titre du post est obligatoire');
        }

        $content = trim((string) $post->getContent());
        if ($content === '') {
            throw new \InvalidArgumentException('Le contenu du post est obligatoire');
        }

        if ($this->textLength($content) < 10) {
            throw new \InvalidArgumentException('Le contenu du post doit contenir au moins 10 caracteres');
        }

        if ($post->getCommunity() === null) {
            throw new \InvalidArgumentException('La communaute est obligatoire');
        }

        return true;
    }

    private function textLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value);
        }

        return strlen($value);
    }
}

