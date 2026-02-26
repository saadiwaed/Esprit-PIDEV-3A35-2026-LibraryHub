<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226223544 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute last_reminder_sent_at sur loan pour éviter les doublons de rappels SMS.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan ADD last_reminder_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan DROP last_reminder_sent_at');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

