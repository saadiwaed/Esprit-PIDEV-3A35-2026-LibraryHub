-- LibraryHub - Schéma SQL complet (tables + relations)
-- Exécuter dans MySQL/MariaDB (phpMyAdmin ou ligne de commande).
-- Charset: utf8mb4. Adapter le nom de la base si besoin.

CREATE DATABASE IF NOT EXISTS libreryhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE libreryhub;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- 1. Tables sans FK vers d'autres tables applicatives
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `role` (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    UNIQUE INDEX UNIQ_57698A6A5E237E06 (name),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS `user` (
    id INT AUTO_INCREMENT NOT NULL,
    email VARCHAR(180) NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address VARCHAR(500) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    last_login_at DATETIME DEFAULT NULL,
    email_verified_at DATETIME DEFAULT NULL,
    UNIQUE INDEX UNIQ_8D93D649E7927C74 (email),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS category (
    id_cat INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description_cat LONGTEXT DEFAULT NULL,
    icon VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id_cat)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS author (
    id INT AUTO_INCREMENT NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS book (
    id INT AUTO_INCREMENT NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS book_copy (
    id INT AUTO_INCREMENT NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS notification (
    id INT AUTO_INCREMENT NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS review (
    id INT AUTO_INCREMENT NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS reward (
    id INT AUTO_INCREMENT NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- ---------------------------------------------------------------------------
-- 2. Tables dépendant de user / role
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS user_role (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    INDEX IDX_2DE8C6A3A76ED395 (user_id),
    INDEX IDX_2DE8C6A3D60322AC (role_id),
    PRIMARY KEY (user_id, role_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS reading_profile (
    id INT AUTO_INCREMENT NOT NULL,
    favorite_genres JSON DEFAULT NULL,
    preferred_languages JSON DEFAULT NULL,
    reading_goal_per_month INT DEFAULT NULL,
    total_books_read INT DEFAULT 0 NOT NULL,
    average_rating DOUBLE PRECISION DEFAULT NULL,
    user_id INT NOT NULL,
    UNIQUE INDEX UNIQ_C5CE393AA76ED395 (user_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

-- ---------------------------------------------------------------------------
-- 3. Book -> category, author
-- ---------------------------------------------------------------------------

ALTER TABLE book
    ADD CONSTRAINT FK_CBE5A33112469DE2 FOREIGN KEY (category_id) REFERENCES category (id_cat),
    ADD CONSTRAINT FK_CBE5A331F675F31B FOREIGN KEY (author_id) REFERENCES author (id);

-- ---------------------------------------------------------------------------
-- 4. Clubs (user founder, created_by)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS clubs (
    id INT AUTO_INCREMENT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT NOT NULL,
    category VARCHAR(100) NOT NULL,
    meeting_date DATETIME NOT NULL,
    meeting_location VARCHAR(255) NOT NULL,
    capacity INT NOT NULL,
    is_private TINYINT DEFAULT 0 NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_date DATETIME NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    founder_id INT NOT NULL,
    created_by_id INT DEFAULT NULL,
    INDEX IDX_A5BD312319113B3C (founder_id),
    INDEX IDX_A5BD3123B03A8386 (created_by_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE clubs
    ADD CONSTRAINT FK_A5BD312319113B3C FOREIGN KEY (founder_id) REFERENCES `user` (id),
    ADD CONSTRAINT FK_A5BD3123B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id);

-- ---------------------------------------------------------------------------
-- 5. Events (user created_by)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description LONGTEXT NOT NULL,
    start_date_time DATETIME NOT NULL,
    end_date_time DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    capacity INT NOT NULL,
    registration_deadline DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_date DATETIME NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    type VARCHAR(50) NOT NULL,
    created_by_id INT NOT NULL,
    INDEX IDX_5387574AB03A8386 (created_by_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE events
    ADD CONSTRAINT FK_5387574AB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id);

-- ---------------------------------------------------------------------------
-- 6. Tables de liaison Club / Event / User
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS club_members (
    club_id INT NOT NULL,
    user_id INT NOT NULL,
    INDEX IDX_48E8777D61190A32 (club_id),
    INDEX IDX_48E8777DA76ED395 (user_id),
    PRIMARY KEY (club_id, user_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS club_organizes_events (
    club_id INT NOT NULL,
    event_id INT NOT NULL,
    INDEX IDX_A6A93AD361190A32 (club_id),
    INDEX IDX_A6A93AD371F7E88B (event_id),
    PRIMARY KEY (club_id, event_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS event_registrations (
    id INT AUTO_INCREMENT NOT NULL,
    registered_at DATETIME NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    status VARCHAR(255) NOT NULL,
    attended_at DATETIME DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    notes LONGTEXT DEFAULT NULL,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    INDEX IDX_7787E14BA76ED395 (user_id),
    INDEX IDX_7787E14B71F7E88B (event_id),
    UNIQUE INDEX unique_registration (user_id, event_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE club_members
    ADD CONSTRAINT FK_48E8777D61190A32 FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_48E8777DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE;

ALTER TABLE club_organizes_events
    ADD CONSTRAINT FK_A6A93AD361190A32 FOREIGN KEY (club_id) REFERENCES clubs (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_A6A93AD371F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE;

ALTER TABLE event_registrations
    ADD CONSTRAINT FK_7787E14BA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id),
    ADD CONSTRAINT FK_7787E14B71F7E88B FOREIGN KEY (event_id) REFERENCES events (id);

-- ---------------------------------------------------------------------------
-- 7. Community (puis created_by_id)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS community (
    id INT AUTO_INCREMENT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description LONGTEXT NOT NULL,
    purpose VARCHAR(255) NOT NULL,
    rules LONGTEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT NULL,
    welcome_message LONGTEXT DEFAULT NULL,
    contact_email VARCHAR(180) DEFAULT NULL,
    is_public TINYINT DEFAULT 1 NOT NULL,
    status VARCHAR(20) NOT NULL,
    member_count INT DEFAULT 0 NOT NULL,
    post_count INT DEFAULT 0 NOT NULL,
    created_at DATETIME NOT NULL,
    created_by_id INT DEFAULT NULL,
    INDEX IDX_D87FEC4BB03A8386 (created_by_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE community
    ADD CONSTRAINT FK_D87FEC4BB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS community_members (
    community_id INT NOT NULL,
    user_id INT NOT NULL,
    INDEX IDX_1164357861190A32 (community_id),
    INDEX IDX_11643578A76ED395 (user_id),
    PRIMARY KEY (community_id, user_id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE community_members
    ADD CONSTRAINT FK_1164357861190A32 FOREIGN KEY (community_id) REFERENCES community (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_11643578A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE;

-- ---------------------------------------------------------------------------
-- 8. Post (community, created_by)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS post (
    id INT AUTO_INCREMENT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    spoiler_warning TINYINT DEFAULT 0 NOT NULL,
    is_pinned TINYINT DEFAULT 0 NOT NULL,
    allow_comments TINYINT DEFAULT 1 NOT NULL,
    external_url VARCHAR(500) DEFAULT NULL,
    comment_count INT DEFAULT 0 NOT NULL,
    like_count INT DEFAULT 0 NOT NULL,
    dislike_count INT DEFAULT 0 NOT NULL,
    created_at DATETIME NOT NULL,
    community_id INT NOT NULL,
    created_by_id INT DEFAULT NULL,
    INDEX IDX_5A8A6C8DFDA7B0BF (community_id),
    INDEX IDX_5A8A6C8DB03A8386 (created_by_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE post
    ADD CONSTRAINT FK_5A8A6C8DFDA7B0BF FOREIGN KEY (community_id) REFERENCES community (id),
    ADD CONSTRAINT FK_5A8A6C8DB03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- 9. Attachment, comments, reactions, report (post / user)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS attachment (
    id INT AUTO_INCREMENT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    post_id INT NOT NULL,
    INDEX IDX_795FD9BB4B89032C (post_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS post_comment (
    id INT AUTO_INCREMENT NOT NULL,
    post_id INT NOT NULL,
    created_by_id INT DEFAULT NULL,
    content LONGTEXT NOT NULL,
    like_count INT DEFAULT 0 NOT NULL,
    dislike_count INT DEFAULT 0 NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_6AC8B9E64B89032C (post_id),
    INDEX IDX_6AC8B9E6B03A8386 (created_by_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS post_reaction (
    id INT AUTO_INCREMENT NOT NULL,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    type VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_52826EE44B89032C (post_id),
    INDEX IDX_52826EE4A76ED395 (user_id),
    UNIQUE INDEX uniq_post_reaction_user (post_id, user_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS comment_reaction (
    id INT AUTO_INCREMENT NOT NULL,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    type VARCHAR(10) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX IDX_8BB1BBCBF8697D13 (comment_id),
    INDEX IDX_8BB1BBCBA76ED395 (user_id),
    UNIQUE INDEX uniq_comment_reaction_user (comment_id, user_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS post_report (
    id INT AUTO_INCREMENT NOT NULL,
    post_id INT NOT NULL,
    reporter_id INT NOT NULL,
    reviewed_by_id INT DEFAULT NULL,
    reason LONGTEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    moderator_decision VARCHAR(30) DEFAULT NULL,
    moderator_decision_reason LONGTEXT DEFAULT NULL,
    INDEX IDX_7C8E45004B89032C (post_id),
    INDEX IDX_7C8E4500E1AE15B2 (reporter_id),
    INDEX IDX_7C8E4500DAADC4DE (reviewed_by_id),
    UNIQUE INDEX uniq_post_report_post_reporter (post_id, reporter_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE attachment
    ADD CONSTRAINT FK_795FD9BB4B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE;

ALTER TABLE post_comment
    ADD CONSTRAINT FK_6AC8B9E64B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_6AC8B9E6B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id) ON DELETE SET NULL;

ALTER TABLE post_reaction
    ADD CONSTRAINT FK_52826EE44B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_52826EE4A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE;

ALTER TABLE comment_reaction
    ADD CONSTRAINT FK_8BB1BBCBF8697D13 FOREIGN KEY (comment_id) REFERENCES post_comment (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_8BB1BBCBA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE;

ALTER TABLE post_report
    ADD CONSTRAINT FK_7C8E45004B89032C FOREIGN KEY (post_id) REFERENCES post (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_7C8E4500E1AE15B2 FOREIGN KEY (reporter_id) REFERENCES `user` (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_7C8E4500DAADC4DE FOREIGN KEY (reviewed_by_id) REFERENCES `user` (id) ON DELETE SET NULL;

-- ---------------------------------------------------------------------------
-- 10. User_role, reading_profile
-- ---------------------------------------------------------------------------

ALTER TABLE user_role
    ADD CONSTRAINT FK_2DE8C6A3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE,
    ADD CONSTRAINT FK_2DE8C6A3D60322AC FOREIGN KEY (role_id) REFERENCES role (id) ON DELETE CASCADE;

ALTER TABLE reading_profile
    ADD CONSTRAINT FK_C5CE393AA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id);

-- ---------------------------------------------------------------------------
-- 11. Loan (book_copy, user)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS loan (
    id INT AUTO_INCREMENT NOT NULL,
    checkout_time DATETIME NOT NULL,
    due_date DATE NOT NULL,
    return_date DATETIME DEFAULT NULL,
    status VARCHAR(255) NOT NULL,
    renewal_count INT NOT NULL,
    notes LONGTEXT DEFAULT NULL,
    book_copy_id INT NOT NULL,
    member_id INT NOT NULL,
    INDEX IDX_C5D30D033B550FE4 (book_copy_id),
    INDEX IDX_C5D30D037597D3FE (member_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE loan
    ADD CONSTRAINT FK_C5D30D033B550FE4 FOREIGN KEY (book_copy_id) REFERENCES book_copy (id),
    ADD CONSTRAINT FK_C5D30D037597D3FE FOREIGN KEY (member_id) REFERENCES `user` (id);

-- ---------------------------------------------------------------------------
-- 12. Penalty, renewal (loan)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS penalty (
    id INT AUTO_INCREMENT NOT NULL,
    amount DOUBLE PRECISION NOT NULL,
    daily_rate NUMERIC(10, 2) DEFAULT 0.50 NOT NULL,
    late_days INT DEFAULT 0 NOT NULL,
    reason VARCHAR(255) NOT NULL,
    issue_date DATE NOT NULL,
    notes LONGTEXT DEFAULT NULL,
    waived TINYINT NOT NULL,
    status VARCHAR(255) NOT NULL,
    loan_id INT NOT NULL,
    INDEX IDX_AFE28FD8CE73868F (loan_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS renewal (
    id INT AUTO_INCREMENT NOT NULL,
    previous_due_date DATE NOT NULL,
    new_due_date DATE NOT NULL,
    renewed_at DATETIME NOT NULL,
    renewal_number INT NOT NULL,
    loan_id INT NOT NULL,
    INDEX IDX_FD0447C8CE73868F (loan_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE penalty
    ADD CONSTRAINT FK_AFE28FD8CE73868F FOREIGN KEY (loan_id) REFERENCES loan (id);

ALTER TABLE renewal
    ADD CONSTRAINT FK_FD0447C8CE73868F FOREIGN KEY (loan_id) REFERENCES loan (id);

-- ---------------------------------------------------------------------------
-- 13. Reading challenges (club, user) et participants (user, challenge)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS reading_challenges (
    id INT AUTO_INCREMENT NOT NULL,
    goal VARCHAR(255) NOT NULL,
    type VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL,
    reward VARCHAR(255) DEFAULT NULL,
    rules LONGTEXT NOT NULL,
    difficulty VARCHAR(50) NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    created_date DATETIME NOT NULL,
    club_id INT DEFAULT NULL,
    created_by_id INT NOT NULL,
    INDEX IDX_F6AA72E661190A32 (club_id),
    INDEX IDX_F6AA72E6B03A8386 (created_by_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE IF NOT EXISTS challenge_participants (
    id INT AUTO_INCREMENT NOT NULL,
    joined_at DATETIME NOT NULL,
    books_read INT NOT NULL,
    completed_at DATETIME DEFAULT NULL,
    status VARCHAR(255) NOT NULL,
    participant_id INT NOT NULL,
    challenge_id INT NOT NULL,
    INDEX IDX_C4C0030B9D1C3019 (participant_id),
    INDEX IDX_C4C0030B98A21AC6 (challenge_id),
    UNIQUE INDEX unique_participant (participant_id, challenge_id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE reading_challenges
    ADD CONSTRAINT FK_F6AA72E661190A32 FOREIGN KEY (club_id) REFERENCES clubs (id),
    ADD CONSTRAINT FK_F6AA72E6B03A8386 FOREIGN KEY (created_by_id) REFERENCES `user` (id);

ALTER TABLE challenge_participants
    ADD CONSTRAINT FK_C4C0030B9D1C3019 FOREIGN KEY (participant_id) REFERENCES `user` (id),
    ADD CONSTRAINT FK_C4C0030B98A21AC6 FOREIGN KEY (challenge_id) REFERENCES reading_challenges (id);

-- ---------------------------------------------------------------------------
-- 14. Messenger (Symfony)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS messenger_messages (
    id BIGINT AUTO_INCREMENT NOT NULL,
    body LONGTEXT NOT NULL,
    headers LONGTEXT NOT NULL,
    queue_name VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL,
    available_at DATETIME NOT NULL,
    delivered_at DATETIME DEFAULT NULL,
    INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id),
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
