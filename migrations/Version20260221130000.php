<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add community/post creators and community membership subscriptions';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE community ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE community ADD CONSTRAINT FK_D87FEC4BB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_D87FEC4BB03A8386 ON community (created_by_id)');

        $this->addSql('ALTER TABLE post ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_5A8A6C8DB03A8386 ON post (created_by_id)');

        $this->addSql('CREATE TABLE community_members (community_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_1164357861190A32 (community_id), INDEX IDX_11643578A76ED395 (user_id), PRIMARY KEY(community_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE community_members ADD CONSTRAINT FK_1164357861190A32 FOREIGN KEY (community_id) REFERENCES community (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_members ADD CONSTRAINT FK_11643578A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE community_members DROP FOREIGN KEY FK_1164357861190A32');
        $this->addSql('ALTER TABLE community_members DROP FOREIGN KEY FK_11643578A76ED395');
        $this->addSql('DROP TABLE community_members');

        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DB03A8386');
        $this->addSql('DROP INDEX IDX_5A8A6C8DB03A8386 ON post');
        $this->addSql('ALTER TABLE post DROP created_by_id');

        $this->addSql('ALTER TABLE community DROP FOREIGN KEY FK_D87FEC4BB03A8386');
        $this->addSql('DROP INDEX IDX_D87FEC4BB03A8386 ON community');
        $this->addSql('ALTER TABLE community DROP created_by_id');
    }
}
