<?php
namespace App\Enum;

enum ParticipationStatus: string
{
    case ACTIVE = 'active';
    case COMPLETED = 'completed';
    case WITHDRAWN = 'withdrawn';
    case DISQUALIFIED = 'disqualified';

    public function getLabel(): string
    {
        return match($this) {
            self::ACTIVE => 'Actif',
            self::COMPLETED => 'Termine',
            self::WITHDRAWN => 'Retire',
            self::DISQUALIFIED => 'Disqualifie',
        };
    }
}
