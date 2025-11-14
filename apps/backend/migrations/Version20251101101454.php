<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251101101454 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Creates statistics tables: page_views, article_stats_daily, site_stats_daily, sessions';
    }

    public function up(Schema $schema): void
    {
        // Table: page_views - Raw pageview tracking data
        $this->addSql('CREATE TABLE page_views (
            id BIGSERIAL PRIMARY KEY,
            article_id INT NULL REFERENCES articles(id) ON DELETE SET NULL,
            visitor_id VARCHAR(255) NOT NULL,
            ip_address INET,
            user_agent TEXT,
            referrer TEXT,
            category_id INT NULL REFERENCES categories(id) ON DELETE SET NULL,
            viewed_at TIMESTAMP DEFAULT NOW(),
            session_duration INT
        )');

        $this->addSql('CREATE INDEX idx_article_views ON page_views(article_id, viewed_at)');
        $this->addSql('CREATE INDEX idx_visitor ON page_views(visitor_id)');
        $this->addSql('CREATE INDEX idx_viewed_at ON page_views(viewed_at)');

        // Table: article_stats_daily - Aggregated daily stats per article
        $this->addSql('CREATE TABLE article_stats_daily (
            id SERIAL PRIMARY KEY,
            article_id INT NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
            date DATE NOT NULL,
            views INT DEFAULT 0,
            unique_visitors INT DEFAULT 0,
            avg_reading_time INT,
            completion_rate DECIMAL(5,2),
            UNIQUE(article_id, date)
        )');

        $this->addSql('CREATE INDEX idx_article_date ON article_stats_daily(article_id, date)');
        $this->addSql('CREATE INDEX idx_date ON article_stats_daily(date)');

        // Table: site_stats_daily - Site-wide daily statistics
        $this->addSql('CREATE TABLE site_stats_daily (
            id SERIAL PRIMARY KEY,
            date DATE NOT NULL UNIQUE,
            total_visits INT DEFAULT 0,
            unique_visitors INT DEFAULT 0,
            new_visitors INT DEFAULT 0,
            bounce_rate DECIMAL(5,2),
            avg_session_duration INT
        )');

        $this->addSql('CREATE INDEX idx_site_stats_date ON site_stats_daily(date)');

        // Table: sessions - User session tracking
        $this->addSql('CREATE TABLE sessions (
            id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
            visitor_id VARCHAR(255) NOT NULL,
            ip_address INET,
            user_agent TEXT,
            referrer TEXT,
            started_at TIMESTAMP DEFAULT NOW(),
            ended_at TIMESTAMP,
            page_count INT DEFAULT 0,
            duration INT
        )');

        $this->addSql('CREATE INDEX idx_started_at ON sessions(started_at)');
        $this->addSql('CREATE INDEX idx_visitor_id ON sessions(visitor_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS sessions CASCADE');
        $this->addSql('DROP TABLE IF EXISTS site_stats_daily CASCADE');
        $this->addSql('DROP TABLE IF EXISTS article_stats_daily CASCADE');
        $this->addSql('DROP TABLE IF EXISTS page_views CASCADE');
    }
}
