<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251101061320 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add UNIQUE constraints to slug columns in articles and categories tables (DECIZIE #2)';
    }

    public function up(Schema $schema): void
    {
        // Add UNIQUE constraint to categories.slug
        // This ensures globally unique slugs across all locales for categories
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3AF34668989D9B62 ON categories (slug)');

        // Add UNIQUE constraint to articles.slug
        // This ensures globally unique slugs across all locales for articles
        $this->addSql('CREATE UNIQUE INDEX UNIQ_BFDD3168989D9B62 ON articles (slug)');
    }

    public function down(Schema $schema): void
    {
        // Remove UNIQUE constraint from articles.slug
        $this->addSql('DROP INDEX UNIQ_BFDD3168989D9B62');

        // Remove UNIQUE constraint from categories.slug
        $this->addSql('DROP INDEX UNIQ_3AF34668989D9B62');
    }
}
