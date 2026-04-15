<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260415050203 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop orphaned story_clusters columns removed from entity';
    }

    public function up(Schema $schema): void
    {
        // Drop orphaned columns — properties were removed from StoryCluster entity
        // but no migration was created at that time.
        $this->addSql('ALTER TABLE story_clusters DROP COLUMN IF EXISTS last_verified_at');
        $this->addSql('ALTER TABLE story_clusters DROP COLUMN IF EXISTS score_breakdown');
        $this->addSql('ALTER TABLE story_clusters DROP COLUMN IF EXISTS previous_summary');
        $this->addSql('ALTER TABLE story_clusters DROP COLUMN IF EXISTS previous_key_facts');
        $this->addSql('ALTER TABLE story_clusters DROP COLUMN IF EXISTS previous_snapshot_at');

        // NOTE: uniq_pr_source_url is a partial index (WHERE source_url IS NOT NULL)
        // that Doctrine cannot represent via attributes. We keep it intentionally —
        // it is excluded from schema diff via doctrine.yaml schema_filter.
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE story_clusters ADD last_verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE story_clusters ADD score_breakdown JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE story_clusters ADD previous_summary TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE story_clusters ADD previous_key_facts JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE story_clusters ADD previous_snapshot_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }
}
