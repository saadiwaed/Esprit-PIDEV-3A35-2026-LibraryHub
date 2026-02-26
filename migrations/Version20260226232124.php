<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226232124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute last_sms_sent_at et penalty_last_notified_at sur loan pour les rappels SMS automatiques.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan ADD last_sms_sent_at DATETIME DEFAULT NULL, ADD penalty_last_notified_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan DROP last_sms_sent_at, DROP penalty_last_notified_at');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

