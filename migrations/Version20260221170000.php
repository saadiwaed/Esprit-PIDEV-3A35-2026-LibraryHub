<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add comments and reactions for posts/comments in forum module';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post ADD like_count INT DEFAULT 0 NOT NULL, ADD dislike_count INT DEFAULT 0 NOT NULL');

        $this->addSql('CREATE TABLE post_comment (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, created_by_id INT DEFAULT NULL, content LONGTEXT NOT NULL, like_count INT DEFAULT 0 NOT NULL, dislike_count INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_6AC8B9E64B89032C (post_id), INDEX IDX_6AC8B9E6B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_6AC8B9E64B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_6AC8B9E6B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE post_reaction (id INT AUTO_INCREMENT NOT NULL, post_id INT NOT NULL, user_id INT NOT NULL, type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_52826EE44B89032C (post_id), INDEX IDX_52826EE4A76ED395 (user_id), UNIQUE INDEX uniq_post_reaction_user (post_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE post_reaction ADD CONSTRAINT FK_52826EE44B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_reaction ADD CONSTRAINT FK_52826EE4A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE comment_reaction (id INT AUTO_INCREMENT NOT NULL, comment_id INT NOT NULL, user_id INT NOT NULL, type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_8BB1BBCBF8697D13 (comment_id), INDEX IDX_8BB1BBCBA76ED395 (user_id), UNIQUE INDEX uniq_comment_reaction_user (comment_id, user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE comment_reaction ADD CONSTRAINT FK_8BB1BBCBF8697D13 FOREIGN KEY (comment_id) REFERENCES post_comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_reaction ADD CONSTRAINT FK_8BB1BBCBA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE comment_reaction DROP FOREIGN KEY FK_8BB1BBCBF8697D13');
        $this->addSql('ALTER TABLE comment_reaction DROP FOREIGN KEY FK_8BB1BBCBA76ED395');
        $this->addSql('DROP TABLE comment_reaction');

        $this->addSql('ALTER TABLE post_reaction DROP FOREIGN KEY FK_52826EE44B89032C');
        $this->addSql('ALTER TABLE post_reaction DROP FOREIGN KEY FK_52826EE4A76ED395');
        $this->addSql('DROP TABLE post_reaction');

        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_6AC8B9E64B89032C');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_6AC8B9E6B03A8386');
        $this->addSql('DROP TABLE post_comment');

        $this->addSql('ALTER TABLE post DROP like_count, DROP dislike_count');
    }
}
