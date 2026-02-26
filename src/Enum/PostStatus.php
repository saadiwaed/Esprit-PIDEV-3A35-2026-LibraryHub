<?php

namespace App\Enum;

enum PostStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case HIDDEN = 'hidden';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::PUBLISHED => 'Publie',
            self::HIDDEN => 'Masque',
            self::ARCHIVED => 'Archive',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::DRAFT => 'bg-secondary',
            self::PUBLISHED => 'bg-success',
            self::HIDDEN => 'bg-dark',
            self::ARCHIVED => 'bg-info',
        };
    }
}
