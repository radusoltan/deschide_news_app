<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * T52.11 (Sprint 52, ADR-019 D5) — Drop articles.source_cluster_id.
 *
 * Column has no FK constraint (audited 2026-04-18) — plain nullable integer.
 * 0 of 14 041 rows had a non-null value → no data loss.
 */
final class Version20260418060002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T52.11 — drop articles.source_cluster_id column (ADR-019 D5)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE articles DROP COLUMN IF EXISTS source_cluster_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE articles ADD source_cluster_id INTEGER DEFAULT NULL');
    }
}
