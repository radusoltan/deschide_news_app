<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed 6 NotebookLM AppSettings keys (Sprint 51a T51a.4).
 */
final class Version20260416161500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed 6 notebooklm.* AppSettings keys for topic notebook sync configuration';
    }

    public function up(Schema $schema): void
    {
        $settings = [
            ['notebooklm.enabled', 'false'],
            ['notebooklm.sync.enabled', 'false'],
            ['notebooklm.sync.max_sources', '300'],
            ['notebooklm.sync.lookback_days', '30'],
            ['notebooklm.factcheck.enabled', 'false'],
            ['notebooklm.factcheck.cache_ttl', '3600'],
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
            'notebooklm.enabled',
            'notebooklm.sync.enabled',
            'notebooklm.sync.max_sources',
            'notebooklm.sync.lookback_days',
            'notebooklm.factcheck.enabled',
            'notebooklm.factcheck.cache_ttl',
        ];

        foreach ($keys as $key) {
            $this->addSql('DELETE FROM app_settings WHERE key = :key', ['key' => $key]);
        }
    }
}
