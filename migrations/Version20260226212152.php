<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226212152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute phone_number sur loan_request (obligatoire) et loan (optionnel).';
    }

    public function up(Schema $schema): void
    {
        // loan_request.phone_number (NOT NULL) + backfill depuis user.phone si possible.
        $this->addSql('ALTER TABLE loan_request ADD phone_number VARCHAR(15) DEFAULT NULL');
        $this->addSql(
            'UPDATE loan_request lr
            INNER JOIN `user` u ON lr.member_id = u.id
            SET lr.phone_number = CASE
                WHEN u.phone REGEXP \'^\\\\+216[0-9]{8}$\' THEN u.phone
                WHEN u.phone REGEXP \'^216[0-9]{8}$\' THEN CONCAT(\'+\', u.phone)
                WHEN u.phone REGEXP \'^[0-9]{8}$\' THEN CONCAT(\'+216\', u.phone)
                ELSE \'+21600000000\'
            END
            WHERE lr.phone_number IS NULL'
        );
        $this->addSql('ALTER TABLE loan_request MODIFY phone_number VARCHAR(15) NOT NULL');

        // loan.phone_number (nullable) + backfill depuis user.phone quand c\'est un numéro tunisien valide.
        $this->addSql('ALTER TABLE loan ADD phone_number VARCHAR(15) DEFAULT NULL');
        $this->addSql(
            'UPDATE loan l
            INNER JOIN `user` u ON l.member_id = u.id
            SET l.phone_number = CASE
                WHEN u.phone REGEXP \'^\\\\+216[0-9]{8}$\' THEN u.phone
                WHEN u.phone REGEXP \'^216[0-9]{8}$\' THEN CONCAT(\'+\', u.phone)
                WHEN u.phone REGEXP \'^[0-9]{8}$\' THEN CONCAT(\'+216\', u.phone)
                ELSE NULL
            END
            WHERE l.phone_number IS NULL'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan_request DROP phone_number');
        $this->addSql('ALTER TABLE loan DROP phone_number');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}

