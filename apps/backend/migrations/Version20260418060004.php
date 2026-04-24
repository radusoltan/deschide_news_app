<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * T52.11 (Sprint 52, ADR-019 D5) — AppSettings cleanup.
 *
 * Deletes:
 *   - 5 cluster_* keys listed in AppSettingsFixture removal manifest.
 *     (Pre-check 2026-04-18 showed 0 of these present in dev DB; DELETE is
 *     a defensive no-op there, but protects older environments / staging.)
 *   - article_generation.use_topic_window — feature flag retired by T52.12
 *     since the legacy StoryCluster generation path is wholesale-removed.
 */
final class Version20260418060004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T52.11 — remove cluster_* AppSettings + article_generation.use_topic_window flag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_settings WHERE key LIKE 'cluster\\_%' ESCAPE '\\'");
        $this->addSql("DELETE FROM app_settings WHERE key = 'article_generation.use_topic_window'");
    }

    public function down(Schema $schema): void
    {
        // Restore cluster_* defaults per pre-T52.11 baseline (values captured from
        // historical fixture at src/DataFixtures/AppSettingsFixture.php docblock).
        $this->addSql("
            INSERT INTO app_settings (key, value, updated_at) VALUES
                ('cluster_semantic_min_confidence', '0.70', NOW()),
                ('cluster_semantic_verification_enabled', 'true', NOW()),
                ('cluster_mlt_min_score', '0.60', NOW()),
                ('cluster_mlt_minimum_should_match', '60%', NOW()),
                ('cluster_temporal_window_hours', '72', NOW())
            ON CONFLICT (key) DO NOTHING
        ");
        $this->addSql("
            INSERT INTO app_settings (key, value, updated_at) VALUES
                ('article_generation.use_topic_window', 'true', NOW())
            ON CONFLICT (key) DO NOTHING
        ");
    }
}
