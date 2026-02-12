<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260211233849 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE challenge_participants CHANGE completed_at completed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE clubs CHANGE image image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE community CHANGE icon icon VARCHAR(50) DEFAULT NULL, CHANGE contact_email contact_email VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE event_registrations CHANGE description description VARCHAR(255) DEFAULT NULL, CHANGE attended_at attended_at DATETIME DEFAULT NULL, CHANGE location location VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE events CHANGE image image VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE post CHANGE external_url external_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE reading_challenges CHANGE reward reward VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users CHANGE roles roles JSON NOT NULL, CHANGE phone phone VARCHAR(20) DEFAULT NULL, CHANGE address address VARCHAR(255) DEFAULT NULL, CHANGE city city VARCHAR(100) DEFAULT NULL, CHANGE zip_code zip_code VARCHAR(10) DEFAULT NULL, CHANGE country country VARCHAR(100) DEFAULT NULL, CHANGE birth_date birth_date DATE DEFAULT NULL, CHANGE profile_image profile_image VARCHAR(255) DEFAULT NULL, CHANGE last_login_at last_login_at DATETIME DEFAULT NULL, CHANGE verification_token verification_token VARCHAR(100) DEFAULT NULL, CHANGE verification_token_expires_at verification_token_expires_at DATETIME DEFAULT NULL, CHANGE reset_token reset_token VARCHAR(100) DEFAULT NULL, CHANGE reset_token_expires_at reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE challenge_participants CHANGE completed_at completed_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE clubs CHANGE image image VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE community CHANGE icon icon VARCHAR(50) DEFAULT \'NULL\', CHANGE contact_email contact_email VARCHAR(180) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE events CHANGE image image VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE event_registrations CHANGE description description VARCHAR(255) DEFAULT \'NULL\', CHANGE attended_at attended_at DATETIME DEFAULT \'NULL\', CHANGE location location VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE post CHANGE external_url external_url VARCHAR(500) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE reading_challenges CHANGE reward reward VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE users CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE phone phone VARCHAR(20) DEFAULT \'NULL\', CHANGE address address VARCHAR(255) DEFAULT \'NULL\', CHANGE city city VARCHAR(100) DEFAULT \'NULL\', CHANGE zip_code zip_code VARCHAR(10) DEFAULT \'NULL\', CHANGE country country VARCHAR(100) DEFAULT \'NULL\', CHANGE birth_date birth_date DATE DEFAULT \'NULL\', CHANGE profile_image profile_image VARCHAR(255) DEFAULT \'NULL\', CHANGE last_login_at last_login_at DATETIME DEFAULT \'NULL\', CHANGE verification_token verification_token VARCHAR(100) DEFAULT \'NULL\', CHANGE verification_token_expires_at verification_token_expires_at DATETIME DEFAULT \'NULL\', CHANGE reset_token reset_token VARCHAR(100) DEFAULT \'NULL\', CHANGE reset_token_expires_at reset_token_expires_at DATETIME DEFAULT \'NULL\'');
    }
}
