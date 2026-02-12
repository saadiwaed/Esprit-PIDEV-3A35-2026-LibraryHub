<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute daily_rate et late_days a penalty pour la penalite retard cumulee.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE penalty ADD daily_rate NUMERIC(10, 2) DEFAULT '0.50' NOT NULL, ADD late_days INT DEFAULT 0 NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE penalty DROP daily_rate, DROP late_days');
    }
}
