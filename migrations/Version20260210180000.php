<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Catalogue: Category, Author columns, Book columns and relations.
 */
final class Version20260210180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogue: category table, author and book columns';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE category (id_cat INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, description_cat LONGTEXT DEFAULT NULL, icon VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id_cat)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE author ADD firstname VARCHAR(255) NOT NULL, ADD lastname VARCHAR(255) NOT NULL, ADD biography LONGTEXT DEFAULT NULL, ADD photo VARCHAR(500) DEFAULT NULL, ADD nationality VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE book ADD title VARCHAR(500) DEFAULT NULL, ADD description LONGTEXT DEFAULT NULL, ADD publisher VARCHAR(255) DEFAULT NULL, ADD publication_year INT DEFAULT NULL, ADD page_count INT DEFAULT NULL, ADD language VARCHAR(50) DEFAULT NULL, ADD cover_image VARCHAR(500) DEFAULT NULL, ADD status VARCHAR(50) DEFAULT \'available\' NOT NULL, ADD created_at DATETIME DEFAULT NULL, ADD category_id INT DEFAULT NULL, ADD author_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE book ADD CONSTRAINT FK_CBE5A33112469DE2 FOREIGN KEY (category_id) REFERENCES category (id_cat)');
        $this->addSql('ALTER TABLE book ADD CONSTRAINT FK_CBE5A331F675F31B FOREIGN KEY (author_id) REFERENCES author (id)');
        $this->addSql('CREATE INDEX IDX_CBE5A33112469DE2 ON book (category_id)');
        $this->addSql('CREATE INDEX IDX_CBE5A331F675F31B ON book (author_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE book DROP FOREIGN KEY FK_CBE5A33112469DE2');
        $this->addSql('ALTER TABLE book DROP FOREIGN KEY FK_CBE5A331F675F31B');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP INDEX IDX_CBE5A33112469DE2 ON book');
        $this->addSql('DROP INDEX IDX_CBE5A331F675F31B ON book');
        $this->addSql('ALTER TABLE book DROP title, DROP description, DROP publisher, DROP publication_year, DROP page_count, DROP language, DROP cover_image, DROP status, DROP created_at, DROP category_id, DROP author_id');
        $this->addSql('ALTER TABLE author DROP firstname, DROP lastname, DROP biography, DROP photo, DROP nationality');
    }
}
