<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute desired_loan_date / desired_return_date sur loan_request.';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = method_exists($this->connection, 'createSchemaManager')
            ? $this->connection->createSchemaManager()
            : $this->connection->getSchemaManager();

        $columns = [];
        foreach ($schemaManager->listTableColumns('loan_request') as $column) {
            $columns[strtolower($column->getName())] = $column;
        }

        if (!isset($columns['desired_loan_date'])) {
            $this->addSql('ALTER TABLE loan_request ADD desired_loan_date DATE DEFAULT NULL');
        }

        if (!isset($columns['desired_return_date'])) {
            $this->addSql('ALTER TABLE loan_request ADD desired_return_date DATE DEFAULT NULL');
        }

        if (isset($columns['title_or_reference'])) {
            $this->addSql('ALTER TABLE loan_request DROP title_or_reference');
        }

        // Backfill for existing rows, then enforce NOT NULL.
        $this->addSql('UPDATE loan_request SET desired_loan_date = DATE(requested_at) WHERE desired_loan_date IS NULL');
        $this->addSql('UPDATE loan_request SET desired_return_date = DATE_ADD(desired_loan_date, INTERVAL 7 DAY) WHERE desired_return_date IS NULL');
        $this->addSql('ALTER TABLE loan_request CHANGE desired_loan_date desired_loan_date DATE NOT NULL');
        $this->addSql('ALTER TABLE loan_request CHANGE desired_return_date desired_return_date DATE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE loan_request DROP desired_loan_date');
        $this->addSql('ALTER TABLE loan_request DROP desired_return_date');
    }
}
