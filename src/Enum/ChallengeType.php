<?php
namespace App\Enum;

enum ChallengeType: string
{
    case READING = 'reading';
    case WRITING = 'writing';
    case REVIEW = 'review';
    case ATTENDANCE = 'attendance';
    case COMMUNITY = 'community';
    case CREATIVE = 'creative';

    public function getLabel(): string
    {
        return match($this) {
            self::READING => 'Lecture',
            self::WRITING => 'Écriture',
            self::REVIEW => 'Critique',
            self::ATTENDANCE => 'Participation',
            self::COMMUNITY => 'Communauté',
            self::CREATIVE => 'Créatif',
        };
    }
    
    public function getIcon(): string
    {
        return match($this) {
            self::READING => 'fas fa-book',
            self::WRITING => 'fas fa-pen',
            self::REVIEW => 'fas fa-star',
            self::ATTENDANCE => 'fas fa-user-check',
            self::COMMUNITY => 'fas fa-users',
            self::CREATIVE => 'fas fa-palette',
        };
    }
}