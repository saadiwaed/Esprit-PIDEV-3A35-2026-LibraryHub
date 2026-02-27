<?php

namespace App\Enum;

enum EventStatus: string
{
    case UPCOMING = 'upcoming';
    case ONGOING = 'ongoing';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function getLabel(): string
    {
        return match($this) {
            self::UPCOMING => 'Ã€ venir',
            self::ONGOING => 'En cours',
            self::COMPLETED => 'Termine',
            self::CANCELLED => 'Annule',
        };
    }
    
}
