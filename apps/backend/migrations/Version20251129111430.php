<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251129111430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing index on page_views.category_id for improved JOIN performance';
    }

    public function up(Schema $schema): void
    {
        // Index was already created manually using CREATE INDEX CONCURRENTLY
        // This migration documents the change for schema consistency
        // If index doesn't exist, create it
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_page_views_category ON page_views (category_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove the category_id index if rolling back
        $this->addSql('DROP INDEX IF EXISTS idx_page_views_category');
    }
}
