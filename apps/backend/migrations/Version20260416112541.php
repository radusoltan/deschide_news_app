<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Topic entity extension: status enum, isStoryLeaf, lifecycle timestamps.
 *
 * Also cleans pre-existing schema drift (partial index, column comments).
 *
 * Refs: T49.1, ADR-015
 */
final class Version20260416112541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add status, is_story_leaf, lifecycle_started_at, lifecycle_ended_at to topics';
    }

    public function up(Schema $schema): void
    {
        // Topic entity extension (ADR-015)
        $this->addSql('ALTER TABLE topics ADD status VARCHAR(20) DEFAULT \'active\' NOT NULL');
        $this->addSql('ALTER TABLE topics ADD is_story_leaf BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE topics ADD lifecycle_started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE topics ADD lifecycle_ended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_topic_status ON topics (status)');

        // Clean pre-existing schema drift (partial index not in entity mapping)
        $this->addSql('DROP INDEX IF EXISTS idx_topic_is_sensitive');

        // Clean Doctrine 2→3 column comment drift
        $this->addSql('COMMENT ON COLUMN press_releases.enriched_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN curation_suggestions.suggested_at IS \'\'');
        $this->addSql('COMMENT ON COLUMN curation_suggestions.resolved_at IS \'\'');

        // Recreate unique index without partial WHERE clause (matches entity mapping)
        $this->addSql('DROP INDEX IF EXISTS uniq_pr_source_url');
        $this->addSql('CREATE UNIQUE INDEX uniq_pr_source_url ON press_releases (source_url)');
    }

    public function down(Schema $schema): void
    {
        // Revert topic fields
        $this->addSql('DROP INDEX idx_topic_status');
        $this->addSql('ALTER TABLE topics DROP status');
        $this->addSql('ALTER TABLE topics DROP is_story_leaf');
        $this->addSql('ALTER TABLE topics DROP lifecycle_started_at');
        $this->addSql('ALTER TABLE topics DROP lifecycle_ended_at');
        $this->addSql('CREATE INDEX idx_topic_is_sensitive ON topics (is_sensitive) WHERE (is_sensitive = true)');

        // Restore column comments
        $this->addSql('COMMENT ON COLUMN press_releases.enriched_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN curation_suggestions.suggested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN curation_suggestions.resolved_at IS \'(DC2Type:datetime_immutable)\'');

        // Restore partial unique index
        $this->addSql('DROP INDEX uniq_pr_source_url');
        $this->addSql('CREATE UNIQUE INDEX uniq_pr_source_url ON press_releases (source_url) WHERE (source_url IS NOT NULL)');
    }
}
