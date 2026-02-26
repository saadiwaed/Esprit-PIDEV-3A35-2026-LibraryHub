<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add post reporting and moderation audit fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE post_report (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, reporter_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, reason LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, moderator_decision VARCHAR(30) DEFAULT NULL, moderator_decision_reason LONGTEXT DEFAULT NULL, INDEX IDX_7C8E45004B89032C (post_id), INDEX IDX_7C8E4500E1AE15B2 (reporter_id), INDEX IDX_7C8E4500DAADC4DE (reviewed_by_id), UNIQUE INDEX uniq_post_report_post_reporter (post_id, reporter_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE post_report ADD CONSTRAINT FK_7C8E45004B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_report ADD CONSTRAINT FK_7C8E4500E1AE15B2 FOREIGN KEY (reporter_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_report ADD CONSTRAINT FK_7C8E4500DAADC4DE FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post_report DROP FOREIGN KEY FK_7C8E45004B89032C');
        $this->addSql('ALTER TABLE post_report DROP FOREIGN KEY FK_7C8E4500E1AE15B2');
        $this->addSql('ALTER TABLE post_report DROP FOREIGN KEY FK_7C8E4500DAADC4DE');
        $this->addSql('DROP TABLE post_report');
    }
}
