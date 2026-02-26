<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260225134433 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE renewal_request (id INT AUTO_INCREMENT NOT NULL, requested_at DATETIME NOT NULL, status VARCHAR(20) NOT NULL, notes LONGTEXT DEFAULT NULL, loan_id INT NOT NULL, member_id INT NOT NULL, INDEX IDX_4C05AF6DCE73868F (loan_id), INDEX IDX_4C05AF6D7597D3FE (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE renewal_request ADD CONSTRAINT FK_4C05AF6DCE73868F FOREIGN KEY (loan_id) REFERENCES loan (id)');
        $this->addSql('ALTER TABLE renewal_request ADD CONSTRAINT FK_4C05AF6D7597D3FE FOREIGN KEY (member_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE renewal_request DROP FOREIGN KEY FK_4C05AF6DCE73868F');
        $this->addSql('ALTER TABLE renewal_request DROP FOREIGN KEY FK_4C05AF6D7597D3FE');
        $this->addSql('DROP TABLE renewal_request');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
