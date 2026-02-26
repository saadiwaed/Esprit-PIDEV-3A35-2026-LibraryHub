<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add threaded replies support with parent_comment_id on post comments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post_comment ADD parent_comment_id INT DEFAULT NULL, ADD INDEX IDX_6AC8B9E6D6B1F5F3 (parent_comment_id)');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_6AC8B9E6D6B1F5F3 FOREIGN KEY (parent_comment_id) REFERENCES post_comment (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_6AC8B9E6D6B1F5F3');
        $this->addSql('DROP INDEX IDX_6AC8B9E6D6B1F5F3 ON post_comment');
        $this->addSql('ALTER TABLE post_comment DROP parent_comment_id');
    }
}
