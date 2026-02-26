<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260209183230 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE author (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book_copy (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE challenge_participants (id INT AUTO_INCREMENT NOT NULL, joined_at VARCHAR(255) NOT NULL, books_read INT NOT NULL, completed_at VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, participant_id INT NOT NULL, challenge_id INT NOT NULL, INDEX IDX_C4C0030B9D1C3019 (participant_id), INDEX IDX_C4C0030B98A21AC6 (challenge_id), UNIQUE INDEX unique_participant (participant_id, challenge_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE clubs (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, category VARCHAR(100) NOT NULL, meeting_date DATETIME NOT NULL, meeting_location VARCHAR(255) NOT NULL, capacity INT NOT NULL, is_private TINYINT DEFAULT 0 NOT NULL, status VARCHAR(20) NOT NULL, created_date DATETIME NOT NULL, image VARCHAR(255) DEFAULT NULL, founder_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_A5BD312319113B3C (founder_id), INDEX IDX_A5BD3123B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE club_members (club_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_48E8777D61190A32 (club_id), INDEX IDX_48E8777DA76ED395 (user_id), PRIMARY KEY (club_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE club_organizes_events (club_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_A6A93AD361190A32 (club_id), INDEX IDX_A6A93AD371F7E88B (event_id), PRIMARY KEY (club_id, event_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE community (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event_registrations (id INT AUTO_INCREMENT NOT NULL, registered_at VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, attended_at VARCHAR(255) DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, user_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_7787E14BA76ED395 (user_id), INDEX IDX_7787E14B71F7E88B (event_id), UNIQUE INDEX unique_registration (user_id, event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE events (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, start_date_time DATETIME NOT NULL, end_date_time DATETIME NOT NULL, location VARCHAR(255) NOT NULL, capacity INT NOT NULL, registration_deadline DATETIME NOT NULL, status VARCHAR(20) NOT NULL, created_date DATETIME NOT NULL, image VARCHAR(255) DEFAULT NULL, created_by_id INT NOT NULL, INDEX IDX_5387574AB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE loan (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE penalty (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reading_challenges (id INT AUTO_INCREMENT NOT NULL, goal VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, reward VARCHAR(255) DEFAULT NULL, rules LONGTEXT NOT NULL, difficulty VARCHAR(50) NOT NULL, start_date VARCHAR(255) NOT NULL, end_date VARCHAR(255) NOT NULL, created_date VARCHAR(255) NOT NULL, club_id INT DEFAULT NULL, created_by_id INT NOT NULL, INDEX IDX_F6AA72E661190A32 (club_id), INDEX IDX_F6AA72E6B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE renewal (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE review (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reward (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, city VARCHAR(100) DEFAULT NULL, zip_code VARCHAR(10) DEFAULT NULL, country VARCHAR(100) DEFAULT NULL, birth_date DATE DEFAULT NULL, profile_image VARCHAR(255) DEFAULT NULL, bio LONGTEXT DEFAULT NULL, status VARCHAR(50) NOT NULL, membership_type VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, is_verified TINYINT NOT NULL, verification_token VARCHAR(100) DEFAULT NULL, verification_token_expires_at DATETIME DEFAULT NULL, reset_token VARCHAR(100) DEFAULT NULL, reset_token_expires_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE challenge_participants ADD CONSTRAINT FK_C4C0030B9D1C3019 FOREIGN KEY (participant_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE challenge_participants ADD CONSTRAINT FK_C4C0030B98A21AC6 FOREIGN KEY (challenge_id) REFERENCES reading_challenges (id)');
        $this->addSql('ALTER TABLE clubs ADD CONSTRAINT FK_A5BD312319113B3C FOREIGN KEY (founder_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE clubs ADD CONSTRAINT FK_A5BD3123B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE club_members ADD CONSTRAINT FK_48E8777D61190A32 FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_members ADD CONSTRAINT FK_48E8777DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_organizes_events ADD CONSTRAINT FK_A6A93AD361190A32 FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_organizes_events ADD CONSTRAINT FK_A6A93AD371F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_registrations ADD CONSTRAINT FK_7787E14BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE event_registrations ADD CONSTRAINT FK_7787E14B71F7E88B FOREIGN KEY (event_id) REFERENCES events (id)');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574AB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE reading_challenges ADD CONSTRAINT FK_F6AA72E661190A32 FOREIGN KEY (club_id) REFERENCES clubs (id)');
        $this->addSql('ALTER TABLE reading_challenges ADD CONSTRAINT FK_F6AA72E6B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE challenge_participants DROP FOREIGN KEY FK_C4C0030B9D1C3019');
        $this->addSql('ALTER TABLE challenge_participants DROP FOREIGN KEY FK_C4C0030B98A21AC6');
        $this->addSql('ALTER TABLE clubs DROP FOREIGN KEY FK_A5BD312319113B3C');
        $this->addSql('ALTER TABLE clubs DROP FOREIGN KEY FK_A5BD3123B03A8386');
        $this->addSql('ALTER TABLE club_members DROP FOREIGN KEY FK_48E8777D61190A32');
        $this->addSql('ALTER TABLE club_members DROP FOREIGN KEY FK_48E8777DA76ED395');
        $this->addSql('ALTER TABLE club_organizes_events DROP FOREIGN KEY FK_A6A93AD361190A32');
        $this->addSql('ALTER TABLE club_organizes_events DROP FOREIGN KEY FK_A6A93AD371F7E88B');
        $this->addSql('ALTER TABLE event_registrations DROP FOREIGN KEY FK_7787E14BA76ED395');
        $this->addSql('ALTER TABLE event_registrations DROP FOREIGN KEY FK_7787E14B71F7E88B');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY FK_5387574AB03A8386');
        $this->addSql('ALTER TABLE reading_challenges DROP FOREIGN KEY FK_F6AA72E661190A32');
        $this->addSql('ALTER TABLE reading_challenges DROP FOREIGN KEY FK_F6AA72E6B03A8386');
        $this->addSql('DROP TABLE author');
        $this->addSql('DROP TABLE book');
        $this->addSql('DROP TABLE book_copy');
        $this->addSql('DROP TABLE challenge_participants');
        $this->addSql('DROP TABLE clubs');
        $this->addSql('DROP TABLE club_members');
        $this->addSql('DROP TABLE club_organizes_events');
        $this->addSql('DROP TABLE community');
        $this->addSql('DROP TABLE event_registrations');
        $this->addSql('DROP TABLE events');
        $this->addSql('DROP TABLE loan');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE penalty');
        $this->addSql('DROP TABLE reading_challenges');
        $this->addSql('DROP TABLE renewal');
        $this->addSql('DROP TABLE review');
        $this->addSql('DROP TABLE reward');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
