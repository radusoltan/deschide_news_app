<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251103124732 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE live_text_ab_tests (id SERIAL NOT NULL, created_by_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, hypothesis TEXT DEFAULT NULL, status VARCHAR(20) NOT NULL, variant_type VARCHAR(50) NOT NULL, control_variant JSON NOT NULL, test_variants JSON NOT NULL, traffic_allocation INT NOT NULL, target_metric VARCHAR(100) NOT NULL, min_sample_size INT DEFAULT NULL, significance_level NUMERIC(3, 2) DEFAULT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, results JSON DEFAULT NULL, winner_variant VARCHAR(100) DEFAULT NULL, confidence_level NUMERIC(5, 2) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F74DAA4BB03A8386 ON live_text_ab_tests (created_by_id)');
        $this->addSql('CREATE INDEX idx_ab_test_status ON live_text_ab_tests (status)');
        $this->addSql('CREATE INDEX idx_ab_test_start ON live_text_ab_tests (start_date)');
        $this->addSql('CREATE TABLE live_text_ab_test_live_texts (live_text_ab_test_id INT NOT NULL, live_text_id INT NOT NULL, PRIMARY KEY(live_text_ab_test_id, live_text_id))');
        $this->addSql('CREATE INDEX IDX_C6F824FCABE0F3A1 ON live_text_ab_test_live_texts (live_text_ab_test_id)');
        $this->addSql('CREATE INDEX IDX_C6F824FCE8507533 ON live_text_ab_test_live_texts (live_text_id)');
        $this->addSql('CREATE TABLE live_text_post_engagements (id SERIAL NOT NULL, post_id INT NOT NULL, user_id INT DEFAULT NULL, session_id VARCHAR(255) NOT NULL, engagement_type VARCHAR(50) NOT NULL, time_spent INT DEFAULT NULL, scroll_depth INT DEFAULT NULL, clicked_element VARCHAR(255) DEFAULT NULL, metadata JSON DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, user_agent VARCHAR(500) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_36D47C7EA76ED395 ON live_text_post_engagements (user_id)');
        $this->addSql('CREATE INDEX idx_post_engagement_post ON live_text_post_engagements (post_id)');
        $this->addSql('CREATE INDEX idx_post_engagement_session ON live_text_post_engagements (session_id)');
        $this->addSql('CREATE INDEX idx_post_engagement_created ON live_text_post_engagements (created_at)');
        $this->addSql('ALTER TABLE live_text_ab_tests ADD CONSTRAINT FK_F74DAA4BB03A8386 FOREIGN KEY (created_by_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_ab_test_live_texts ADD CONSTRAINT FK_C6F824FCABE0F3A1 FOREIGN KEY (live_text_ab_test_id) REFERENCES live_text_ab_tests (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_ab_test_live_texts ADD CONSTRAINT FK_C6F824FCE8507533 FOREIGN KEY (live_text_id) REFERENCES live_texts (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_post_engagements ADD CONSTRAINT FK_36D47C7E4B89032C FOREIGN KEY (post_id) REFERENCES live_text_posts (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE live_text_post_engagements ADD CONSTRAINT FK_36D47C7EA76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE live_text_ab_tests DROP CONSTRAINT FK_F74DAA4BB03A8386');
        $this->addSql('ALTER TABLE live_text_ab_test_live_texts DROP CONSTRAINT FK_C6F824FCABE0F3A1');
        $this->addSql('ALTER TABLE live_text_ab_test_live_texts DROP CONSTRAINT FK_C6F824FCE8507533');
        $this->addSql('ALTER TABLE live_text_post_engagements DROP CONSTRAINT FK_36D47C7E4B89032C');
        $this->addSql('ALTER TABLE live_text_post_engagements DROP CONSTRAINT FK_36D47C7EA76ED395');
        $this->addSql('DROP TABLE live_text_ab_tests');
        $this->addSql('DROP TABLE live_text_ab_test_live_texts');
        $this->addSql('DROP TABLE live_text_post_engagements');
    }
}
