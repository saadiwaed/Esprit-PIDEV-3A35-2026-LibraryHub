<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260226162910 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE club_embeddings (id INT AUTO_INCREMENT NOT NULL, embedding JSON NOT NULL, generated_at DATETIME NOT NULL, club_id INT NOT NULL, UNIQUE INDEX UNIQ_63EE545C61190A32 (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE club_embeddings ADD CONSTRAINT FK_63EE545C61190A32 FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE club_embeddings DROP FOREIGN KEY FK_63EE545C61190A32');
        $this->addSql('DROP TABLE club_embeddings');
    }
}
