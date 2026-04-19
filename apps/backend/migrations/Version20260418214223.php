<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 55 T55.8 — editorial_escalation_log SLA expiry column + enum tightening.
 *
 *  - Adds `expires_at TIMESTAMP(0) WITHOUT TIME ZONE NULL` driving the T55.11
 *    SLA auto-expire scheduler.
 *  - Tightens `category_code` from VARCHAR(40) to VARCHAR(20) to match the
 *    short-code space enforced by App\Enum\Editorial\EscalationCategory.
 *  - Creates `idx_esc_log_expires_pending` as a partial index (WHERE decision IS NULL)
 *    so the 60-second expiry scanner only touches actually-pending rows.
 */
final class Version20260418214223 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sprint 55 T55.8 — editorial_escalation_log: +expires_at + category_code VARCHAR(20) + partial index';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE editorial_escalation_log ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE editorial_escalation_log ALTER category_code TYPE VARCHAR(20)');
        $this->addSql('CREATE INDEX idx_esc_log_expires_pending ON editorial_escalation_log (expires_at) WHERE decision IS NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_esc_log_expires_pending');
        $this->addSql('ALTER TABLE editorial_escalation_log ALTER category_code TYPE VARCHAR(40)');
        $this->addSql('ALTER TABLE editorial_escalation_log DROP expires_at');
    }
}
