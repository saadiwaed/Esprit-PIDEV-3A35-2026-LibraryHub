<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260227011648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE attachment (id INT AUTO_INCREMENT NOT NULL, file_path VARCHAR(255) NOT NULL, post_id INT NOT NULL, INDEX IDX_795FD9BB4B89032C (post_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE author (id INT AUTO_INCREMENT NOT NULL, firstname VARCHAR(255) NOT NULL, lastname VARCHAR(255) NOT NULL, biography LONGTEXT DEFAULT NULL, photo VARCHAR(500) DEFAULT NULL, nationality VARCHAR(100) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(500) NOT NULL, description LONGTEXT DEFAULT NULL, publisher VARCHAR(255) DEFAULT NULL, publication_year INT DEFAULT NULL, page_count INT DEFAULT NULL, language VARCHAR(50) DEFAULT NULL, cover_image VARCHAR(500) DEFAULT NULL, status VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, category_id INT NOT NULL, author_id INT NOT NULL, INDEX IDX_CBE5A33112469DE2 (category_id), INDEX IDX_CBE5A331F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE book_copy (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id_cat INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description_cat LONGTEXT DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id_cat)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE challenge_participants (id INT AUTO_INCREMENT NOT NULL, joined_at VARCHAR(255) NOT NULL, books_read INT NOT NULL, completed_at VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, participant_id INT NOT NULL, challenge_id INT NOT NULL, INDEX IDX_C4C0030B9D1C3019 (participant_id), INDEX IDX_C4C0030B98A21AC6 (challenge_id), UNIQUE INDEX unique_participant (participant_id, challenge_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE club_embeddings (id INT AUTO_INCREMENT NOT NULL, embedding JSON NOT NULL, generated_at DATETIME NOT NULL, club_id INT NOT NULL, UNIQUE INDEX UNIQ_63EE545C61190A32 (club_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE clubs (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, category VARCHAR(100) NOT NULL, meeting_date DATETIME NOT NULL, meeting_location VARCHAR(255) NOT NULL, capacity INT NOT NULL, is_private TINYINT(1) DEFAULT 0 NOT NULL, status VARCHAR(20) NOT NULL, created_date DATETIME NOT NULL, image VARCHAR(255) DEFAULT NULL, founder_id INT NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_A5BD312319113B3C (founder_id), INDEX IDX_A5BD3123B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE club_members (club_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_48E8777D61190A32 (club_id), INDEX IDX_48E8777DA76ED395 (user_id), PRIMARY KEY(club_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE club_organizes_events (club_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_A6A93AD361190A32 (club_id), INDEX IDX_A6A93AD371F7E88B (event_id), PRIMARY KEY(club_id, event_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE comment_reaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, comment_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_B99364F1F8697D13 (comment_id), INDEX IDX_B99364F1A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE community (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT NOT NULL, purpose VARCHAR(255) NOT NULL, rules LONGTEXT DEFAULT NULL, icon VARCHAR(50) DEFAULT NULL, welcome_message LONGTEXT DEFAULT NULL, contact_email VARCHAR(180) DEFAULT NULL, is_public TINYINT(1) DEFAULT 1 NOT NULL, status VARCHAR(20) NOT NULL, member_count INT DEFAULT 0 NOT NULL, post_count INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_1B604033B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE community_members (community_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_6165BBACFDA7B0BF (community_id), INDEX IDX_6165BBACA76ED395 (user_id), PRIMARY KEY(community_id, user_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE event_registrations (id INT AUTO_INCREMENT NOT NULL, registered_at DATETIME NOT NULL, description VARCHAR(255) DEFAULT NULL, status VARCHAR(255) NOT NULL, attended_at DATETIME DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, user_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_7787E14BA76ED395 (user_id), INDEX IDX_7787E14B71F7E88B (event_id), UNIQUE INDEX unique_registration (user_id, event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE events (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, start_date_time DATETIME NOT NULL, end_date_time DATETIME NOT NULL, location VARCHAR(255) NOT NULL, capacity INT NOT NULL, registration_deadline DATETIME NOT NULL, status VARCHAR(20) NOT NULL, created_date DATETIME NOT NULL, image VARCHAR(255) DEFAULT NULL, type VARCHAR(50) NOT NULL, created_by_id INT NOT NULL, INDEX IDX_5387574AB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE loan (id INT AUTO_INCREMENT NOT NULL, checkout_time DATETIME NOT NULL, due_date DATE NOT NULL, return_date DATETIME DEFAULT NULL, status VARCHAR(255) NOT NULL, renewal_count INT NOT NULL, notes LONGTEXT DEFAULT NULL, book_copy_id INT NOT NULL, member_id INT NOT NULL, INDEX IDX_C5D30D033B550FE4 (book_copy_id), INDEX IDX_C5D30D037597D3FE (member_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE loan_request (id INT AUTO_INCREMENT NOT NULL, desired_loan_date DATE NOT NULL, desired_return_date DATE NOT NULL, requested_at DATETIME NOT NULL, status VARCHAR(255) NOT NULL, notes LONGTEXT DEFAULT NULL, member_id INT DEFAULT NULL, book_id INT NOT NULL, INDEX IDX_15D801EB7597D3FE (member_id), INDEX IDX_15D801EB16A2B381 (book_id), INDEX loan_request_status_requested_at_idx (status, requested_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE penalty (id INT AUTO_INCREMENT NOT NULL, amount DOUBLE PRECISION NOT NULL, daily_rate NUMERIC(10, 2) DEFAULT \'0.50\' NOT NULL, late_days INT DEFAULT 0 NOT NULL, reason VARCHAR(255) NOT NULL, issue_date DATE NOT NULL, notes LONGTEXT DEFAULT NULL, waived TINYINT(1) NOT NULL, status VARCHAR(255) NOT NULL, loan_id INT NOT NULL, INDEX IDX_AFE28FD8CE73868F (loan_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, spoiler_warning TINYINT(1) DEFAULT 0 NOT NULL, is_pinned TINYINT(1) DEFAULT 0 NOT NULL, allow_comments TINYINT(1) DEFAULT 1 NOT NULL, external_url VARCHAR(500) DEFAULT NULL, comment_count INT DEFAULT 0 NOT NULL, like_count INT DEFAULT 0 NOT NULL, dislike_count INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, created_by_id INT DEFAULT NULL, community_id INT NOT NULL, INDEX IDX_5A8A6C8DB03A8386 (created_by_id), INDEX IDX_5A8A6C8DFDA7B0BF (community_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_comment (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, like_count INT DEFAULT 0 NOT NULL, dislike_count INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, post_id INT NOT NULL, parent_comment_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, INDEX IDX_A99CE55F4B89032C (post_id), INDEX IDX_A99CE55FBF2AF943 (parent_comment_id), INDEX IDX_A99CE55FB03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_reaction (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL, post_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_1B3A8E564B89032C (post_id), INDEX IDX_1B3A8E56A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE post_report (id INT AUTO_INCREMENT NOT NULL, reason LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, moderator_decision VARCHAR(30) DEFAULT NULL, moderator_decision_reason LONGTEXT DEFAULT NULL, post_id INT NOT NULL, reporter_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, INDEX IDX_F40D93E14B89032C (post_id), INDEX IDX_F40D93E1E1CFE6F5 (reporter_id), INDEX IDX_F40D93E1FC6B21F1 (reviewed_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reading_challenges (id INT AUTO_INCREMENT NOT NULL, goal VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, reward VARCHAR(255) DEFAULT NULL, rules LONGTEXT NOT NULL, difficulty VARCHAR(50) NOT NULL, start_date VARCHAR(255) NOT NULL, end_date VARCHAR(255) NOT NULL, created_date VARCHAR(255) NOT NULL, club_id INT DEFAULT NULL, created_by_id INT NOT NULL, INDEX IDX_F6AA72E661190A32 (club_id), INDEX IDX_F6AA72E6B03A8386 (created_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reading_profile (id INT AUTO_INCREMENT NOT NULL, favorite_genres JSON DEFAULT NULL, preferred_languages JSON DEFAULT NULL, reading_goal_per_month INT DEFAULT NULL, total_books_read INT DEFAULT 0 NOT NULL, average_rating DOUBLE PRECISION DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_C5CE393AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE renewal (id INT AUTO_INCREMENT NOT NULL, previous_due_date DATE NOT NULL, new_due_date DATE NOT NULL, renewed_at DATETIME NOT NULL, renewal_number INT NOT NULL, loan_id INT NOT NULL, INDEX IDX_FD0447C8CE73868F (loan_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE review (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reward (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE role (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, description VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_57698A6A5E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20) DEFAULT NULL, address VARCHAR(500) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, status VARCHAR(20) NOT NULL, created_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL, email_verified_at DATETIME DEFAULT NULL, is_premium TINYINT(1) DEFAULT 0 NOT NULL, face_descriptor LONGTEXT DEFAULT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_role (user_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_2DE8C6A3A76ED395 (user_id), INDEX IDX_2DE8C6A3D60322AC (role_id), PRIMARY KEY(user_id, role_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE attachment ADD CONSTRAINT FK_795FD9BB4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE book ADD CONSTRAINT FK_CBE5A33112469DE2 FOREIGN KEY (category_id) REFERENCES category (id_cat)');
        $this->addSql('ALTER TABLE book ADD CONSTRAINT FK_CBE5A331F675F31B FOREIGN KEY (author_id) REFERENCES author (id)');
        $this->addSql('ALTER TABLE challenge_participants ADD CONSTRAINT FK_C4C0030B9D1C3019 FOREIGN KEY (participant_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE challenge_participants ADD CONSTRAINT FK_C4C0030B98A21AC6 FOREIGN KEY (challenge_id) REFERENCES reading_challenges (id)');
        $this->addSql('ALTER TABLE club_embeddings ADD CONSTRAINT FK_63EE545C61190A32 FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE clubs ADD CONSTRAINT FK_A5BD312319113B3C FOREIGN KEY (founder_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE clubs ADD CONSTRAINT FK_A5BD3123B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE club_members ADD CONSTRAINT FK_48E8777D61190A32 FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_members ADD CONSTRAINT FK_48E8777DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_organizes_events ADD CONSTRAINT FK_A6A93AD361190A32 FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE club_organizes_events ADD CONSTRAINT FK_A6A93AD371F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_reaction ADD CONSTRAINT FK_B99364F1F8697D13 FOREIGN KEY (comment_id) REFERENCES post_comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE comment_reaction ADD CONSTRAINT FK_B99364F1A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community ADD CONSTRAINT FK_1B604033B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE community_members ADD CONSTRAINT FK_6165BBACFDA7B0BF FOREIGN KEY (community_id) REFERENCES community (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE community_members ADD CONSTRAINT FK_6165BBACA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_registrations ADD CONSTRAINT FK_7787E14BA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE event_registrations ADD CONSTRAINT FK_7787E14B71F7E88B FOREIGN KEY (event_id) REFERENCES events (id)');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574AB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D033B550FE4 FOREIGN KEY (book_copy_id) REFERENCES book_copy (id)');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D037597D3FE FOREIGN KEY (member_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE loan_request ADD CONSTRAINT FK_15D801EB7597D3FE FOREIGN KEY (member_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE loan_request ADD CONSTRAINT FK_15D801EB16A2B381 FOREIGN KEY (book_id) REFERENCES book (id)');
        $this->addSql('ALTER TABLE penalty ADD CONSTRAINT FK_AFE28FD8CE73868F FOREIGN KEY (loan_id) REFERENCES loan (id)');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE post ADD CONSTRAINT FK_5A8A6C8DFDA7B0BF FOREIGN KEY (community_id) REFERENCES community (id)');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55F4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55FBF2AF943 FOREIGN KEY (parent_comment_id) REFERENCES post_comment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_comment ADD CONSTRAINT FK_A99CE55FB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE post_reaction ADD CONSTRAINT FK_1B3A8E564B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_reaction ADD CONSTRAINT FK_1B3A8E56A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_report ADD CONSTRAINT FK_F40D93E14B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_report ADD CONSTRAINT FK_F40D93E1E1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE post_report ADD CONSTRAINT FK_F40D93E1FC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reading_challenges ADD CONSTRAINT FK_F6AA72E661190A32 FOREIGN KEY (club_id) REFERENCES clubs (id)');
        $this->addSql('ALTER TABLE reading_challenges ADD CONSTRAINT FK_F6AA72E6B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE reading_profile ADD CONSTRAINT FK_C5CE393AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE renewal ADD CONSTRAINT FK_FD0447C8CE73868F FOREIGN KEY (loan_id) REFERENCES loan (id)');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A3D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attachment DROP FOREIGN KEY FK_795FD9BB4B89032C');
        $this->addSql('ALTER TABLE book DROP FOREIGN KEY FK_CBE5A33112469DE2');
        $this->addSql('ALTER TABLE book DROP FOREIGN KEY FK_CBE5A331F675F31B');
        $this->addSql('ALTER TABLE challenge_participants DROP FOREIGN KEY FK_C4C0030B9D1C3019');
        $this->addSql('ALTER TABLE challenge_participants DROP FOREIGN KEY FK_C4C0030B98A21AC6');
        $this->addSql('ALTER TABLE club_embeddings DROP FOREIGN KEY FK_63EE545C61190A32');
        $this->addSql('ALTER TABLE clubs DROP FOREIGN KEY FK_A5BD312319113B3C');
        $this->addSql('ALTER TABLE clubs DROP FOREIGN KEY FK_A5BD3123B03A8386');
        $this->addSql('ALTER TABLE club_members DROP FOREIGN KEY FK_48E8777D61190A32');
        $this->addSql('ALTER TABLE club_members DROP FOREIGN KEY FK_48E8777DA76ED395');
        $this->addSql('ALTER TABLE club_organizes_events DROP FOREIGN KEY FK_A6A93AD361190A32');
        $this->addSql('ALTER TABLE club_organizes_events DROP FOREIGN KEY FK_A6A93AD371F7E88B');
        $this->addSql('ALTER TABLE comment_reaction DROP FOREIGN KEY FK_B99364F1F8697D13');
        $this->addSql('ALTER TABLE comment_reaction DROP FOREIGN KEY FK_B99364F1A76ED395');
        $this->addSql('ALTER TABLE community DROP FOREIGN KEY FK_1B604033B03A8386');
        $this->addSql('ALTER TABLE community_members DROP FOREIGN KEY FK_6165BBACFDA7B0BF');
        $this->addSql('ALTER TABLE community_members DROP FOREIGN KEY FK_6165BBACA76ED395');
        $this->addSql('ALTER TABLE event_registrations DROP FOREIGN KEY FK_7787E14BA76ED395');
        $this->addSql('ALTER TABLE event_registrations DROP FOREIGN KEY FK_7787E14B71F7E88B');
        $this->addSql('ALTER TABLE events DROP FOREIGN KEY FK_5387574AB03A8386');
        $this->addSql('ALTER TABLE loan DROP FOREIGN KEY FK_C5D30D033B550FE4');
        $this->addSql('ALTER TABLE loan DROP FOREIGN KEY FK_C5D30D037597D3FE');
        $this->addSql('ALTER TABLE loan_request DROP FOREIGN KEY FK_15D801EB7597D3FE');
        $this->addSql('ALTER TABLE loan_request DROP FOREIGN KEY FK_15D801EB16A2B381');
        $this->addSql('ALTER TABLE penalty DROP FOREIGN KEY FK_AFE28FD8CE73868F');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DB03A8386');
        $this->addSql('ALTER TABLE post DROP FOREIGN KEY FK_5A8A6C8DFDA7B0BF');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55F4B89032C');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55FBF2AF943');
        $this->addSql('ALTER TABLE post_comment DROP FOREIGN KEY FK_A99CE55FB03A8386');
        $this->addSql('ALTER TABLE post_reaction DROP FOREIGN KEY FK_1B3A8E564B89032C');
        $this->addSql('ALTER TABLE post_reaction DROP FOREIGN KEY FK_1B3A8E56A76ED395');
        $this->addSql('ALTER TABLE post_report DROP FOREIGN KEY FK_F40D93E14B89032C');
        $this->addSql('ALTER TABLE post_report DROP FOREIGN KEY FK_F40D93E1E1CFE6F5');
        $this->addSql('ALTER TABLE post_report DROP FOREIGN KEY FK_F40D93E1FC6B21F1');
        $this->addSql('ALTER TABLE reading_challenges DROP FOREIGN KEY FK_F6AA72E661190A32');
        $this->addSql('ALTER TABLE reading_challenges DROP FOREIGN KEY FK_F6AA72E6B03A8386');
        $this->addSql('ALTER TABLE reading_profile DROP FOREIGN KEY FK_C5CE393AA76ED395');
        $this->addSql('ALTER TABLE renewal DROP FOREIGN KEY FK_FD0447C8CE73868F');
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A3A76ED395');
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A3D60322AC');
        $this->addSql('DROP TABLE attachment');
        $this->addSql('DROP TABLE author');
        $this->addSql('DROP TABLE book');
        $this->addSql('DROP TABLE book_copy');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE challenge_participants');
        $this->addSql('DROP TABLE club_embeddings');
        $this->addSql('DROP TABLE clubs');
        $this->addSql('DROP TABLE club_members');
        $this->addSql('DROP TABLE club_organizes_events');
        $this->addSql('DROP TABLE comment_reaction');
        $this->addSql('DROP TABLE community');
        $this->addSql('DROP TABLE community_members');
        $this->addSql('DROP TABLE event_registrations');
        $this->addSql('DROP TABLE events');
        $this->addSql('DROP TABLE loan');
        $this->addSql('DROP TABLE loan_request');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE penalty');
        $this->addSql('DROP TABLE post');
        $this->addSql('DROP TABLE post_comment');
        $this->addSql('DROP TABLE post_reaction');
        $this->addSql('DROP TABLE post_report');
        $this->addSql('DROP TABLE reading_challenges');
        $this->addSql('DROP TABLE reading_profile');
        $this->addSql('DROP TABLE renewal');
        $this->addSql('DROP TABLE review');
        $this->addSql('DROP TABLE reward');
        $this->addSql('DROP TABLE role');
        $this->addSql('DROP TABLE `user`');
        $this->addSql('DROP TABLE user_role');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
