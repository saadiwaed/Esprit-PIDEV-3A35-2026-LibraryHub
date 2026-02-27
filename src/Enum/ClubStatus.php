<?php
namespace App\Enum;

enum ClubStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PAUSED = 'paused';
    case ARCHIVED = 'archived';

    public function getLabel(): string
    {
        return match($this) {
            self::ACTIVE => 'Actif',
            self::INACTIVE => 'Inactif',
            self::PAUSED => 'En pause',
            self::ARCHIVED => 'Archive',
        };
    }




}
