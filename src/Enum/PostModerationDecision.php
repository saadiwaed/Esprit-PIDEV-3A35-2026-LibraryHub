<?php

namespace App\Enum;

enum PostModerationDecision: string
{
    case APPROVE = 'approve';
    case HIDE_POST = 'hide_post';
    case REJECT_REPORT = 'reject_report';

    public function getLabel(): string
    {
        return match ($this) {
            self::APPROVE => 'Accepter le signalement (sans masquage) [legacy]',
            self::HIDE_POST => 'Accepter le signalement et masquer le post',
            self::REJECT_REPORT => 'Rejeter le signalement et conserver le post',
        };
    }
}
