<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210095456 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add short_links, short_link_interactions tables and webcode field to articles';
    }

    public function up(Schema $schema): void
    {
        // Create short_links table
        $this->addSql('CREATE TABLE short_links (id SERIAL NOT NULL, article_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, code VARCHAR(50) NOT NULL, original_url VARCHAR(500) NOT NULL, title VARCHAR(255) DEFAULT NULL, click_count INT DEFAULT 0 NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5187011D77153098 ON short_links (code)');
        $this->addSql('CREATE INDEX IDX_5187011D7294869C ON short_links (article_id)');
        $this->addSql('CREATE INDEX IDX_5187011DB03A8386 ON short_links (created_by_id)');
        $this->addSql('CREATE INDEX idx_short_link_code ON short_links (code)');
        $this->addSql('CREATE INDEX idx_short_link_created_at ON short_links (created_at)');
        $this->addSql('CREATE INDEX idx_short_link_click_count ON short_links (click_count)');
        $this->addSql('COMMENT ON COLUMN short_links.created_at IS \'(DC2Type:datetime_immutable)\'');

        // Create short_link_interactions table
        $this->addSql('CREATE TABLE short_link_interactions (id SERIAL NOT NULL, short_link_id INT NOT NULL, clicked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, referrer VARCHAR(500) DEFAULT NULL, country_code VARCHAR(2) DEFAULT NULL, device_type VARCHAR(20) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_sli_short_link ON short_link_interactions (short_link_id)');
        $this->addSql('CREATE INDEX idx_sli_clicked_at ON short_link_interactions (clicked_at)');
        $this->addSql('CREATE INDEX idx_sli_country_code ON short_link_interactions (country_code)');
        $this->addSql('CREATE INDEX idx_sli_device_type ON short_link_interactions (device_type)');
        $this->addSql('CREATE INDEX idx_sli_short_link_clicked ON short_link_interactions (short_link_id, clicked_at)');
        $this->addSql('COMMENT ON COLUMN short_link_interactions.clicked_at IS \'(DC2Type:datetime_immutable)\'');

        // Add foreign key constraints
        $this->addSql('ALTER TABLE short_links ADD CONSTRAINT FK_5187011D7294869C FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE short_links ADD CONSTRAINT FK_5187011DB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE short_link_interactions ADD CONSTRAINT FK_4886F22C605D5D9 FOREIGN KEY (short_link_id) REFERENCES short_links (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Add webcode field to articles
        $this->addSql('ALTER TABLE articles ADD webcode VARCHAR(10) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_ARTICLES_WEBCODE ON articles (webcode)');
        $this->addSql('CREATE INDEX idx_article_webcode ON articles (webcode)');
    }

    public function down(Schema $schema): void
    {
        // Remove webcode from articles
        $this->addSql('DROP INDEX idx_article_webcode');
        $this->addSql('DROP INDEX UNIQ_ARTICLES_WEBCODE');
        $this->addSql('ALTER TABLE articles DROP COLUMN webcode');

        // Drop foreign key constraints
        $this->addSql('ALTER TABLE short_link_interactions DROP CONSTRAINT FK_4886F22C605D5D9');
        $this->addSql('ALTER TABLE short_links DROP CONSTRAINT FK_5187011D7294869C');
        $this->addSql('ALTER TABLE short_links DROP CONSTRAINT FK_5187011DB03A8386');

        // Drop tables
        $this->addSql('DROP TABLE short_link_interactions');
        $this->addSql('DROP TABLE short_links');
    }
}
