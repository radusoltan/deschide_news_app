<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251101113933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_article_date');
        $this->addSql('ALTER TABLE article_stats_daily ALTER views SET NOT NULL');
        $this->addSql('ALTER TABLE article_stats_daily ALTER unique_visitors SET NOT NULL');
        $this->addSql('ALTER INDEX article_stats_daily_article_id_date_key RENAME TO uniq_article_date');
        $this->addSql('ALTER TABLE page_views ALTER ip_address TYPE VARCHAR(45)');
        $this->addSql('ALTER TABLE page_views ALTER viewed_at DROP DEFAULT');
        $this->addSql('ALTER TABLE page_views ALTER viewed_at SET NOT NULL');
        $this->addSql('ALTER TABLE sessions ALTER id DROP DEFAULT');
        $this->addSql('ALTER TABLE sessions ALTER ip_address TYPE VARCHAR(45)');
        $this->addSql('ALTER TABLE sessions ALTER started_at DROP DEFAULT');
        $this->addSql('ALTER TABLE sessions ALTER started_at SET NOT NULL');
        $this->addSql('ALTER TABLE sessions ALTER page_count SET NOT NULL');
        $this->addSql('ALTER TABLE site_stats_daily ALTER total_visits SET NOT NULL');
        $this->addSql('ALTER TABLE site_stats_daily ALTER unique_visitors SET NOT NULL');
        $this->addSql('ALTER TABLE site_stats_daily ALTER new_visitors SET NOT NULL');
        $this->addSql('ALTER INDEX site_stats_daily_date_key RENAME TO uniq_site_date');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE sessions ALTER id SET DEFAULT \'gen_random_uuid()\'');
        $this->addSql('ALTER TABLE sessions ALTER ip_address TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE sessions ALTER started_at SET DEFAULT \'now()\'');
        $this->addSql('ALTER TABLE sessions ALTER started_at DROP NOT NULL');
        $this->addSql('ALTER TABLE sessions ALTER page_count DROP NOT NULL');
        $this->addSql('ALTER TABLE page_views ALTER ip_address TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE page_views ALTER viewed_at SET DEFAULT \'now()\'');
        $this->addSql('ALTER TABLE page_views ALTER viewed_at DROP NOT NULL');
        $this->addSql('ALTER TABLE article_stats_daily ALTER views DROP NOT NULL');
        $this->addSql('ALTER TABLE article_stats_daily ALTER unique_visitors DROP NOT NULL');
        $this->addSql('CREATE INDEX idx_article_date ON article_stats_daily (article_id, date)');
        $this->addSql('ALTER INDEX uniq_article_date RENAME TO article_stats_daily_article_id_date_key');
        $this->addSql('ALTER TABLE site_stats_daily ALTER total_visits DROP NOT NULL');
        $this->addSql('ALTER TABLE site_stats_daily ALTER unique_visitors DROP NOT NULL');
        $this->addSql('ALTER TABLE site_stats_daily ALTER new_visitors DROP NOT NULL');
        $this->addSql('ALTER INDEX uniq_site_date RENAME TO site_stats_daily_date_key');
    }
}
