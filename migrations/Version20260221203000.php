<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Index;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow multiple reports per user/post over time while keeping pending checks at application level';
    }

    public function up(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['post_report'])) {
            return;
        }

        $indexes = $schemaManager->listTableIndexes('post_report');
        $legacyUniqueIndexName = $this->findUniquePostReporterIndexName($indexes);

        if ($legacyUniqueIndexName !== null) {
            $this->addSql(sprintf('DROP INDEX %s ON post_report', $legacyUniqueIndexName));
        }

        if (!isset($indexes['idx_post_report_lookup'])) {
            $this->addSql('CREATE INDEX idx_post_report_lookup ON post_report (post_id, reporter_id, status)');
        }
    }

    public function down(Schema $schema): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['post_report'])) {
            return;
        }

        $indexes = $schemaManager->listTableIndexes('post_report');

        if (isset($indexes['idx_post_report_lookup'])) {
            $this->addSql('DROP INDEX idx_post_report_lookup ON post_report');
        }

        if ($this->findUniquePostReporterIndexName($indexes) === null) {
            $this->addSql('CREATE UNIQUE INDEX uniq_post_report_post_reporter ON post_report (post_id, reporter_id)');
        }
    }

    /**
     * @param array<string, Index> $indexes
     */
    private function findUniquePostReporterIndexName(array $indexes): ?string
    {
        foreach ($indexes as $name => $index) {
            if (!$index->isUnique()) {
                continue;
            }

            $columns = array_map('strtolower', $index->getColumns());
            sort($columns);
            if ($columns === ['post_id', 'reporter_id']) {
                return $name;
            }
        }

        return null;
    }
}
