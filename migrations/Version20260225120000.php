<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add face_descriptor column to user for admin facial recognition.
 */
final class Version20260225120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.face_descriptor for admin facial recognition';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD face_descriptor LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP face_descriptor');
    }
}

