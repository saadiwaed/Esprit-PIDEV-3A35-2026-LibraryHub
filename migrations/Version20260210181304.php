<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210181304 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan ADD checkout_time DATETIME NOT NULL, ADD due_date DATE NOT NULL, ADD return_date DATETIME DEFAULT NULL, ADD status VARCHAR(255) NOT NULL, ADD renewal_count INT NOT NULL, ADD notes LONGTEXT DEFAULT NULL, ADD late_fee DOUBLE PRECISION DEFAULT NULL, ADD book_copy_id INT NOT NULL, ADD member_id INT NOT NULL, ADD INDEX IDX_C5D30D033B550FE4 (book_copy_id), ADD INDEX IDX_C5D30D037597D3FE (member_id)');
        $this->addSql('ALTER TABLE penalty ADD amount DOUBLE PRECISION NOT NULL, ADD reason LONGTEXT NOT NULL, ADD issue_date DATE NOT NULL, ADD notes LONGTEXT DEFAULT NULL, ADD waived TINYINT NOT NULL, ADD status VARCHAR(255) NOT NULL, ADD loan_id INT NOT NULL, ADD INDEX IDX_AFE28FD8CE73868F (loan_id)');
        $this->addSql('ALTER TABLE renewal ADD previous_due_date DATE NOT NULL, ADD new_due_date DATE NOT NULL, ADD renewed_at DATETIME NOT NULL, ADD renewal_number INT NOT NULL, ADD loan_id INT NOT NULL, ADD INDEX IDX_FD0447C8CE73868F (loan_id)');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D033B550FE4 FOREIGN KEY (book_copy_id) REFERENCES book_copy (id)');
        $this->addSql('ALTER TABLE loan ADD CONSTRAINT FK_C5D30D037597D3FE FOREIGN KEY (member_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE penalty ADD CONSTRAINT FK_AFE28FD8CE73868F FOREIGN KEY (loan_id) REFERENCES loan (id)');
        $this->addSql('ALTER TABLE renewal ADD CONSTRAINT FK_FD0447C8CE73868F FOREIGN KEY (loan_id) REFERENCES loan (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan DROP FOREIGN KEY FK_C5D30D033B550FE4');
        $this->addSql('ALTER TABLE loan DROP FOREIGN KEY FK_C5D30D037597D3FE');
        $this->addSql('ALTER TABLE penalty DROP FOREIGN KEY FK_AFE28FD8CE73868F');
        $this->addSql('ALTER TABLE renewal DROP FOREIGN KEY FK_FD0447C8CE73868F');
        $this->addSql('ALTER TABLE loan DROP COLUMN checkout_time, DROP COLUMN due_date, DROP COLUMN return_date, DROP COLUMN status, DROP COLUMN renewal_count, DROP COLUMN notes, DROP COLUMN late_fee, DROP COLUMN book_copy_id, DROP COLUMN member_id, DROP INDEX IDX_C5D30D033B550FE4, DROP INDEX IDX_C5D30D037597D3FE');
        $this->addSql('ALTER TABLE penalty DROP COLUMN amount, DROP COLUMN reason, DROP COLUMN issue_date, DROP COLUMN notes, DROP COLUMN waived, DROP COLUMN status, DROP COLUMN loan_id, DROP INDEX IDX_AFE28FD8CE73868F');
        $this->addSql('ALTER TABLE renewal DROP COLUMN previous_due_date, DROP COLUMN new_due_date, DROP COLUMN renewed_at, DROP COLUMN renewal_number, DROP COLUMN loan_id, DROP INDEX IDX_FD0447C8CE73868F');
    }
}
