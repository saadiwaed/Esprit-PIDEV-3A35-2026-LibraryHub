<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260226013308 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs last_email_reminder_sent_at / last_sms_reminder_sent_at sur loan, loan_request et renewal_request.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan ADD last_email_reminder_sent_at DATETIME DEFAULT NULL, ADD last_sms_reminder_sent_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE loan_request ADD last_email_reminder_sent_at DATETIME DEFAULT NULL, ADD last_sms_reminder_sent_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE renewal_request ADD last_email_reminder_sent_at DATETIME DEFAULT NULL, ADD last_sms_reminder_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE loan DROP last_email_reminder_sent_at, DROP last_sms_reminder_sent_at');
        $this->addSql('ALTER TABLE loan_request DROP last_email_reminder_sent_at, DROP last_sms_reminder_sent_at');
        $this->addSql('ALTER TABLE renewal_request DROP last_email_reminder_sent_at, DROP last_sms_reminder_sent_at');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
