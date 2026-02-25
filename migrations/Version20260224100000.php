<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add missing tables: reading_challenges and challenge_participants (FK to `user`).
 */
final class Version20260224100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reading_challenges and challenge_participants tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reading_challenges (id INT AUTO_INCREMENT NOT NULL, goal VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, reward VARCHAR(255) DEFAULT NULL, rules LONGTEXT NOT NULL, difficulty VARCHAR(50) NOT NULL, start_date DATETIME NOT NULL, end_date DATETIME NOT NULL, created_date DATETIME NOT NULL, club_id INT DEFAULT NULL, created_by_id INT NOT NULL, INDEX IDX_F6AA72E661190A32 (club_id), INDEX IDX_F6AA72E6B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE challenge_participants (id INT AUTO_INCREMENT NOT NULL, joined_at DATETIME NOT NULL, books_read INT NOT NULL, completed_at DATETIME DEFAULT NULL, status VARCHAR(255) NOT NULL, participant_id INT NOT NULL, challenge_id INT NOT NULL, INDEX IDX_C4C0030B9D1C3019 (participant_id), INDEX IDX_C4C0030B98A21AC6 (challenge_id), UNIQUE INDEX unique_participant (participant_id, challenge_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reading_challenges ADD CONSTRAINT FK_F6AA72E661190A32 FOREIGN KEY (club_id) REFERENCES clubs (id)');
        $this->addSql('ALTER TABLE reading_challenges ADD CONSTRAINT FK_F6AA72E6B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE challenge_participants ADD CONSTRAINT FK_C4C0030B9D1C3019 FOREIGN KEY (participant_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE challenge_participants ADD CONSTRAINT FK_C4C0030B98A21AC6 FOREIGN KEY (challenge_id) REFERENCES reading_challenges (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE challenge_participants DROP FOREIGN KEY FK_C4C0030B9D1C3019');
        $this->addSql('ALTER TABLE challenge_participants DROP FOREIGN KEY FK_C4C0030B98A21AC6');
        $this->addSql('ALTER TABLE reading_challenges DROP FOREIGN KEY FK_F6AA72E661190A32');
        $this->addSql('ALTER TABLE reading_challenges DROP FOREIGN KEY FK_F6AA72E6B03A8386');
        $this->addSql('DROP TABLE challenge_participants');
        $this->addSql('DROP TABLE reading_challenges');
    }
}
