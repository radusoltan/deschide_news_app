<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * T52.11 (Sprint 52, ADR-019 D5) — Drop press_releases.source_cluster_id.
 *
 * Column has no FK constraint (audited 2026-04-18) — plain nullable integer.
 * 1 row had a non-null value (PR #216, the T52.10 legacy-path sample).
 * That reference is intentionally discarded per ADR-019 D5 + Decision 4
 * (audit trail preserved via source_name string 'AI:cluster#8').
 */
final class Version20260418060001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T52.11 — drop press_releases.source_cluster_id column (ADR-019 D5)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases DROP COLUMN IF EXISTS source_cluster_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE press_releases ADD source_cluster_id INTEGER DEFAULT NULL');
    }
}
