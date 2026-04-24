<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seed AppSettings with 10 briefing eligibility gate keys (ADR-016 D6).
 */
final class Version20260416140001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed 10 briefing AppSettings keys for per-cadence eligibility thresholds';
    }

    public function up(Schema $schema): void
    {
        $settings = [
            ['briefing.enabled', 'true'],
            ['briefing.hourly.enabled', 'true'],
            ['briefing.daily.enabled', 'true'],
            ['briefing.weekly.enabled', 'true'],
            ['briefing.hourly.min_pr_count', '3'],
            ['briefing.daily.min_pr_count', '5'],
            ['briefing.weekly.min_pr_count', '10'],
            ['briefing.hourly.min_avg_relevance', '3.0'],
            ['briefing.daily.min_avg_relevance', '2.5'],
            ['briefing.weekly.min_avg_relevance', '2.0'],
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
            'briefing.enabled',
            'briefing.hourly.enabled',
            'briefing.daily.enabled',
            'briefing.weekly.enabled',
            'briefing.hourly.min_pr_count',
            'briefing.daily.min_pr_count',
            'briefing.weekly.min_pr_count',
            'briefing.hourly.min_avg_relevance',
            'briefing.daily.min_avg_relevance',
            'briefing.weekly.min_avg_relevance',
        ];

        foreach ($keys as $key) {
            $this->addSql('DELETE FROM app_settings WHERE key = :key', ['key' => $key]);
        }
    }
}
