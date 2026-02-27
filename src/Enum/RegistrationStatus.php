<?php
namespace App\Enum;

enum RegistrationStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case WAITLISTED = 'waitlisted';
    case CANCELLED = 'cancelled';
    case ATTENDED = 'attended';
    case NO_SHOW = 'no_show';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirme',
            self::WAITLISTED => 'Liste d\'attente',
            self::CANCELLED => 'Annule',
            self::ATTENDED => 'A participe',
            self::NO_SHOW => 'Absent',
        };
    }
}
