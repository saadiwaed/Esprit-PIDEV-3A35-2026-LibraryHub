<?php
namespace App\Enum;

enum EventTypes: string
{
    case CONFERENCE = 'conference';
    case WORKSHOP = 'workshop';
    case SEMINAR = 'seminar';
    case MEETUP = 'meetup';
    case PARTY = 'party';
    case SPORTS = 'sports';
    case CULTURAL = 'cultural';
    case EDUCATIONAL = 'educational';
    case CHARITY = 'charity';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match($this) {
            self::CONFERENCE => 'Conférence',
            self::WORKSHOP => 'Atelier',
            self::SEMINAR => 'Séminaire',
            self::MEETUP => 'Rencontre',
            self::PARTY => 'Fête',
            self::SPORTS => 'Sport',
            self::CULTURAL => 'Culturel',
            self::EDUCATIONAL => 'Éducatif',
            self::CHARITY => 'Caritatif',
            self::OTHER => 'Autre',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::CONFERENCE => 'microphone',
            self::WORKSHOP => 'tools',
            self::SEMINAR => 'users',
            self::MEETUP => 'handshake',
            self::PARTY => 'glass-cheers',
            self::SPORTS => 'futbol',
            self::CULTURAL => 'palette',
            self::EDUCATIONAL => 'graduation-cap',
            self::CHARITY => 'heart',
            self::OTHER => 'tag',
        };
    }
}