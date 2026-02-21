<?php
namespace App\Enum;

enum EventTypes: string
{
    case BOOK_CLUB = 'book_club';
    case READING = 'reading';
    case AUTHOR_MEETING = 'author_meeting';
    case BOOK_SIGNING = 'book_signing';
    case WORKSHOP = 'workshop';
    case LITERARY_CONFERENCE = 'literary_conference';
    case DEBATE = 'debate';
    case POETRY = 'poetry';
    case BOOK_FAIR = 'book_fair';
    case STORYTELLING = 'storytelling';
    case CHILDREN = 'children';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match($this) {
            self::BOOK_CLUB => 'Club de lecture',
            self::READING => 'Lecture publique',
            self::AUTHOR_MEETING => 'Rencontre avec un auteur',
            self::BOOK_SIGNING => 'Dédicace',
            self::WORKSHOP => 'Atelier d\'écriture',
            self::LITERARY_CONFERENCE => 'Conférence littéraire',
            self::DEBATE => 'Débat / Discussion',
            self::POETRY => 'Poésie / Slam',
            self::BOOK_FAIR => 'Salon du livre',
            self::STORYTELLING => 'Conte / Récit',
            self::CHILDREN => 'Lecture jeunesse',
            self::OTHER => 'Autre',
        };
    }

    public function getIcon(): string
    {
        return match($this) {
            self::BOOK_CLUB => 'book-open',
            self::READING => 'book',
            self::AUTHOR_MEETING => 'person-circle',
            self::BOOK_SIGNING => 'pen',
            self::WORKSHOP => 'pencil',
            self::LITERARY_CONFERENCE => 'microphone',
            self::DEBATE => 'chat-dots',
            self::POETRY => 'feather',
            self::BOOK_FAIR => 'shop',
            self::STORYTELLING => 'chat-quote',
            self::CHILDREN => 'balloon',
            self::OTHER => 'tag',
        };
    }
}