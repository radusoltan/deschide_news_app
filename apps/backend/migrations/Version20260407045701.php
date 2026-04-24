<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260407045701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add publishedLocales TEXT[] field with GIN index and migrate existing data';
    }

    public function up(Schema $schema): void
    {
        // Add column with default
        $this->addSql('ALTER TABLE articles ADD published_locales TEXT[] DEFAULT \'{ro}\' NOT NULL');

        // Data migration: articles with complete translations get all 3 locales
        $this->addSql("UPDATE articles SET published_locales = '{ro,en,ru}' WHERE translation_status = 'complete'");

        // GIN index for fast @> (array contains) queries
        $this->addSql('CREATE INDEX idx_article_published_locales ON articles USING GIN (published_locales)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE articles DROP published_locales');
    }
}
