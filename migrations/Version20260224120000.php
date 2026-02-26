<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add is_premium to user for Stripe subscription status.
 */
final class Version20260224120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user.is_premium for premium subscription (Stripe)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD is_premium TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP is_premium');
    }
}
