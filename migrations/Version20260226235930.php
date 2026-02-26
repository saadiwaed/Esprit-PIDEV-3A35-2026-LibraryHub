<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226235930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute ON DELETE CASCADE sur renewal_request.loan_id pour permettre la suppression d\'un emprunt avec ses demandes de renouvellement.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE renewal_request DROP FOREIGN KEY FK_4C05AF6DCE73868F');
        $this->addSql('ALTER TABLE renewal_request ADD CONSTRAINT FK_4C05AF6DCE73868F FOREIGN KEY (loan_id) REFERENCES loan (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE renewal_request DROP FOREIGN KEY FK_4C05AF6DCE73868F');
        $this->addSql('ALTER TABLE renewal_request ADD CONSTRAINT FK_4C05AF6DCE73868F FOREIGN KEY (loan_id) REFERENCES loan (id)');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

