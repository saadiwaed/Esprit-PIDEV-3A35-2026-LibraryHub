<?php

namespace App\Enum;

enum PostStatus: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match($this) {
            self::DRAFT => 'Brouillon',
            self::PUBLISHED => 'Publié',
            self::ARCHIVED => 'Archivé',
        };
    }

    public function getBadgeClass(): string
    {
        return match($this) {
            self::DRAFT => 'bg-secondary',
            self::PUBLISHED => 'bg-success',
            self::ARCHIVED => 'bg-info',
        };
    }
}