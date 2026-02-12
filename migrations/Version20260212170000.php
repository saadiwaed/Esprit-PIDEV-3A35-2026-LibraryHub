<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime la colonne loan.late_fee devenue obsolete.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan DROP COLUMN IF EXISTS late_fee');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan ADD late_fee DOUBLE PRECISION DEFAULT NULL');
    }
}
