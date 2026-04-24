<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260408041246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sprint 31: Add PressRelease→Source FK, populate from domain matching, drop stale index';
    }

    public function up(Schema $schema): void
    {
        // Drop stale index (schema out of sync from prior sprint)
        $this->addSql('DROP INDEX IF EXISTS idx_article_published_locales');

        // Add source_id FK on press_releases
        $this->addSql('ALTER TABLE press_releases ADD source_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE press_releases ADD CONSTRAINT FK_9B6CA723953C1C61 FOREIGN KEY (source_id) REFERENCES sources (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_9B6CA723953C1C61 ON press_releases (source_id)');

        // Populate source_id from source_publisher_domain matching Source.domain_pattern
        $this->addSql("
            UPDATE press_releases pr
            SET source_id = s.id
            FROM sources s
            WHERE pr.source_publisher_domain IS NOT NULL
              AND pr.source_publisher_domain = s.domain_pattern
              AND pr.source_id IS NULL
        ");

        // Populate source_id from parsed sourceUrl hostname matching Source.domain_pattern
        $this->addSql("
            UPDATE press_releases pr
            SET source_id = s.id
            FROM sources s
            WHERE pr.source_id IS NULL
              AND pr.source_url IS NOT NULL
              AND s.domain_pattern IS NOT NULL
              AND pr.source_url LIKE '%' || s.domain_pattern || '%'
        ");

        // Populate from source_name containing known patterns
        $this->addSql("UPDATE press_releases SET source_id = (SELECT id FROM sources WHERE name = 'Gov.md') WHERE source_id IS NULL AND source_name LIKE 'scrape:gov%'");
        $this->addSql("UPDATE press_releases SET source_id = (SELECT id FROM sources WHERE name = 'UNIAN') WHERE source_id IS NULL AND source_name LIKE 'scrape:unian%'");
        $this->addSql("UPDATE press_releases SET source_id = (SELECT id FROM sources WHERE name = 'Associated Press') WHERE source_id IS NULL AND source_name LIKE 'scrape:ap_news%'");
        $this->addSql("UPDATE press_releases SET source_id = (SELECT id FROM sources WHERE name = 'Ukrinform') WHERE source_id IS NULL AND source_name LIKE 'scrape:ukrinform%'");
        $this->addSql("UPDATE press_releases SET source_id = (SELECT id FROM sources WHERE name = 'Agerpres') WHERE source_id IS NULL AND source_name LIKE 'scrape:agerpres%'");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE INDEX idx_article_published_locales ON articles (published_locales)');
        $this->addSql('ALTER TABLE press_releases DROP CONSTRAINT FK_9B6CA723953C1C61');
        $this->addSql('DROP INDEX IDX_9B6CA723953C1C61');
        $this->addSql('ALTER TABLE press_releases DROP source_id');
    }
}
