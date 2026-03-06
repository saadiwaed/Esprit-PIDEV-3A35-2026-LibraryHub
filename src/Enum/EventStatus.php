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
            self::UPCOMING => 'À venir',
            self::ONGOING => 'En cours',
            self::COMPLETED => 'Terminé',
            self::CANCELLED => 'Annulé',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::UPCOMING => 'primary',
            self::ONGOING => 'success',
            self::COMPLETED => 'secondary',
            self::CANCELLED => 'danger',
        };
    }

    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }
        return $choices;
    }
}