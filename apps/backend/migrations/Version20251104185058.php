<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251104185058 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add composite indexes for query optimization (Articles: 3, Authors: 1, LiveTexts: 3)';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_article_status_published ON articles (status, published_at)');
        $this->addSql('CREATE INDEX idx_article_featured_published ON articles (is_featured, published_at)');
        $this->addSql('CREATE INDEX idx_article_category_status_published ON articles (category_id, status, published_at)');
        $this->addSql('CREATE INDEX idx_author_active_status ON authors (is_active, status)');
        $this->addSql('CREATE INDEX idx_livetext_status_start ON live_texts (status, start_time)');
        $this->addSql('CREATE INDEX idx_livetext_status_end ON live_texts (status, end_time)');
        $this->addSql('CREATE INDEX idx_livetext_category_status_start ON live_texts (category_id, status, start_time)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('DROP INDEX idx_article_status_published');
        $this->addSql('DROP INDEX idx_article_featured_published');
        $this->addSql('DROP INDEX idx_article_category_status_published');
        $this->addSql('DROP INDEX idx_author_active_status');
        $this->addSql('DROP INDEX idx_livetext_status_start');
        $this->addSql('DROP INDEX idx_livetext_status_end');
        $this->addSql('DROP INDEX idx_livetext_category_status_start');
    }
}
