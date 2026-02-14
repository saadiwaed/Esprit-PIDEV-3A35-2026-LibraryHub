<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Migration legacy (désactivée) : ancien ajustement loan_request.';
    }

    public function up(Schema $schema): void
    {
        // No-op: superseded by later migrations.
    }

    public function down(Schema $schema): void
    {
        // No-op.
    }
}
