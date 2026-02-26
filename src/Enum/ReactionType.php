<?php

namespace App\Enum;

enum ReactionType: string
{
    case LIKE = 'like';
    case DISLIKE = 'dislike';

    public function getLabel(): string
    {
        return match ($this) {
            self::LIKE => 'Like',
            self::DISLIKE => 'Dislike',
        };
    }
}
