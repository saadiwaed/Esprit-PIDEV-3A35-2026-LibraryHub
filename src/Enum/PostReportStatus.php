<?php

namespace App\Enum;

enum PostReportStatus: string
{
    case PENDING = 'pending';
    case RESOLVED = 'resolved';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::RESOLVED => 'Resolue',
            self::REJECTED => 'Rejetee',
        };
    }

    public function getBadgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-warning',
            self::RESOLVED => 'bg-success',
            self::REJECTED => 'bg-danger',
        };
    }
}
