<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214201500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend member_id nullable sur loan_request (mode sans authentification).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan_request DROP FOREIGN KEY FK_LOAN_REQUEST_MEMBER');
        $this->addSql('ALTER TABLE loan_request CHANGE member_id member_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE loan_request ADD CONSTRAINT FK_LOAN_REQUEST_MEMBER FOREIGN KEY (member_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan_request DROP FOREIGN KEY FK_LOAN_REQUEST_MEMBER');
        $this->addSql('ALTER TABLE loan_request CHANGE member_id member_id INT NOT NULL');
        $this->addSql('ALTER TABLE loan_request ADD CONSTRAINT FK_LOAN_REQUEST_MEMBER FOREIGN KEY (member_id) REFERENCES users (id)');
    }
}

