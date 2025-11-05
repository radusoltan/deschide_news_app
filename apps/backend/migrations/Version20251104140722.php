<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251104140722 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite index on articles(status, category_id) for optimized category stats queries';
    }

    public function up(Schema $schema): void
    {
        // Add composite index for optimizing category statistics queries
        // This improves performance of queries like: WHERE status = 'published' AND category_id IS NOT NULL
        $this->addSql('CREATE INDEX idx_article_status_category ON articles (status, category_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove composite index
        $this->addSql('DROP INDEX idx_article_status_category');
    }
}
