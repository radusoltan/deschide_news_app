<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * T52.11 (Sprint 52, ADR-019 D5) — Drop story_clusters + its two join tables.
 *
 * Drop order: join tables first (they CASCADE-FK into story_clusters), then parent.
 * All identity sequences owned by the tables drop automatically with them.
 */
final class Version20260418060003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T52.11 — drop story_clusters, story_cluster_press_release, story_cluster_topic (ADR-019 D5)';
    }

    public function up(Schema $schema): void
    {
        // Join tables first (FK CASCADE targets into story_clusters).
        $this->addSql('DROP TABLE IF EXISTS story_cluster_press_release');
        $this->addSql('DROP TABLE IF EXISTS story_cluster_topic');

        // Parent.
        $this->addSql('DROP TABLE IF EXISTS story_clusters');
    }

    public function down(Schema $schema): void
    {
        // Recreate parent first, then join tables with their FKs back to it.
        $this->addSql('
            CREATE TABLE story_clusters (
                id SERIAL NOT NULL,
                primary_headline VARCHAR(500) NOT NULL,
                summary_short TEXT DEFAULT NULL,
                summary_medium TEXT DEFAULT NULL,
                why_it_matters TEXT DEFAULT NULL,
                key_facts JSON DEFAULT NULL,
                importance_score DOUBLE PRECISION DEFAULT 0 NOT NULL,
                source_count INT DEFAULT 0 NOT NULL,
                article_count INT DEFAULT 0 NOT NULL,
                region_tags JSON DEFAULT NULL,
                status VARCHAR(20) DEFAULT \'auto\' NOT NULL,
                first_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                last_updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                promoted_to_press_release BOOLEAN DEFAULT false NOT NULL,
                editorial_boost DOUBLE PRECISION DEFAULT 1 NOT NULL,
                PRIMARY KEY(id)
            )
        ');
        $this->addSql('CREATE INDEX idx_cluster_importance ON story_clusters (importance_score)');
        $this->addSql('CREATE INDEX idx_cluster_status ON story_clusters (status)');
        $this->addSql('CREATE INDEX idx_cluster_first_seen ON story_clusters (first_seen_at)');
        $this->addSql('CREATE INDEX idx_cluster_promoted ON story_clusters (promoted_to_press_release)');

        $this->addSql('
            CREATE TABLE story_cluster_press_release (
                story_cluster_id INT NOT NULL,
                press_release_id INT NOT NULL,
                PRIMARY KEY(story_cluster_id, press_release_id)
            )
        ');
        $this->addSql('CREATE INDEX idx_959a3f4fe491c8b8 ON story_cluster_press_release (story_cluster_id)');
        $this->addSql('CREATE INDEX idx_959a3f4f78750292 ON story_cluster_press_release (press_release_id)');
        $this->addSql('
            ALTER TABLE story_cluster_press_release
            ADD CONSTRAINT fk_959a3f4fe491c8b8 FOREIGN KEY (story_cluster_id) REFERENCES story_clusters (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ');
        $this->addSql('
            ALTER TABLE story_cluster_press_release
            ADD CONSTRAINT fk_959a3f4f78750292 FOREIGN KEY (press_release_id) REFERENCES press_releases (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ');

        $this->addSql('
            CREATE TABLE story_cluster_topic (
                story_cluster_id INT NOT NULL,
                topic_id INT NOT NULL,
                PRIMARY KEY(story_cluster_id, topic_id)
            )
        ');
        $this->addSql('CREATE INDEX idx_cb23d4a4e491c8b8 ON story_cluster_topic (story_cluster_id)');
        $this->addSql('CREATE INDEX idx_cb23d4a41f55203d ON story_cluster_topic (topic_id)');
        $this->addSql('
            ALTER TABLE story_cluster_topic
            ADD CONSTRAINT fk_cb23d4a4e491c8b8 FOREIGN KEY (story_cluster_id) REFERENCES story_clusters (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ');
        $this->addSql('
            ALTER TABLE story_cluster_topic
            ADD CONSTRAINT fk_cb23d4a41f55203d FOREIGN KEY (topic_id) REFERENCES topics (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        ');
    }
}
