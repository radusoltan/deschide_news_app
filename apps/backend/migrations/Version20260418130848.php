<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 54 T54.3 — add `claim_graph_snapshot` JSON column to `source_signals`.
 *
 * Populated by VerificationGate (T54.9) with the ClaimOriginGraph + verdict
 * at decision time, one row per signal in the verified cluster. Nullable
 * because L1-ingested signals (Sprint 53 path) bypass verification until
 * the master switch flips.
 */
final class Version20260418130848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add claim_graph_snapshot JSON column to source_signals (Sprint 54 T54.3)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source_signals ADD claim_graph_snapshot JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source_signals DROP claim_graph_snapshot');
    }
}
