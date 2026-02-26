<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224155924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE loan_request (id INT AUTO_INCREMENT NOT NULL, book_id INT NOT NULL, desired_loan_date DATE NOT NULL, desired_return_date DATE NOT NULL, requested_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, member_id INT NOT NULL, INDEX IDX_15D801EB7597D3FE (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE loan_request ADD CONSTRAINT FK_15D801EB7597D3FE FOREIGN KEY (member_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan_request DROP FOREIGN KEY FK_15D801EB7597D3FE');
        $this->addSql('DROP TABLE loan_request');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
