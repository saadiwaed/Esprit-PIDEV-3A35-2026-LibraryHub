<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table loan_request pour les demandes d’emprunt.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE loan_request (id INT AUTO_INCREMENT NOT NULL, member_id INT NOT NULL, book_id INT NOT NULL, requested_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, INDEX IDX_LOAN_REQUEST_MEMBER (member_id), INDEX IDX_LOAN_REQUEST_BOOK (book_id), INDEX loan_request_status_requested_at_idx (status, requested_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE loan_request ADD CONSTRAINT FK_LOAN_REQUEST_MEMBER FOREIGN KEY (member_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE loan_request ADD CONSTRAINT FK_LOAN_REQUEST_BOOK FOREIGN KEY (book_id) REFERENCES book (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE loan_request');
    }
}

