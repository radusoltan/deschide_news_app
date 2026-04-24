<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed 3 LLM configuration AppSettings keys for briefing writer (ADR-016 D5).
 */
final class Version20260416141001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed 3 briefing LLM AppSettings keys (polish_enabled, gemini_timeout, claude_timeout)';
    }

    public function up(Schema $schema): void
    {
        $settings = [
            ['briefing.llm.polish_enabled', 'true'],
            ['briefing.llm.gemini_timeout', '120'],
            ['briefing.llm.claude_timeout', '120'],
        ];

        foreach ($settings as [$key, $value]) {
            $this->addSql(
                'INSERT INTO app_settings (key, value, updated_at) VALUES (:key, :value, NOW()) ON CONFLICT (key) DO NOTHING',
                ['key' => $key, 'value' => $value],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $keys = [
            'briefing.llm.polish_enabled',
            'briefing.llm.gemini_timeout',
            'briefing.llm.claude_timeout',
        ];

        foreach ($keys as $key) {
            $this->addSql('DELETE FROM app_settings WHERE key = :key', ['key' => $key]);
        }
    }
}
