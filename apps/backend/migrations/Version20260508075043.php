<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Follow-up to Version20260508063850 (T60.X-NUKE-EDITORIAL).
 *
 * Drop the orphan `agent.journalistic_translator.enabled` AppSetting key.
 * Per multi-agent review (P1.4): the flag had zero consumers post-nuke
 * (`TierResolver::isEnabled()` was never wired to AgentDispatcher), so
 * flipping it had no operational effect — silent dead config.
 *
 * Refs: T60.X-NUKE-EDITORIAL multi-agent review feedback
 */
final class Version20260508075043 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'T60.X follow-up: drop orphan agent.journalistic_translator.enabled AppSetting key.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM app_settings WHERE key = 'agent.journalistic_translator.enabled'");
    }

    public function down(Schema $schema): void
    {
        // Reseed with the previous default value if rolling back.
        $this->addSql("INSERT INTO app_settings (key, value, updated_at) VALUES ('agent.journalistic_translator.enabled', 'true', NOW()) ON CONFLICT (key) DO NOTHING");
    }
}
