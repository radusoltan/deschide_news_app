<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AppSetting;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Seeds runtime AppSettings keys with sensible defaults.
 *
 * Populates:
 *  - `article_generation.*` family consumed by the topic-window article
 *    generator (ADR-019 D1 + D7). The T52.10 `use_topic_window` feature flag
 *    was retired in T52.12 when the legacy StoryCluster path was removed
 *    wholesale, so it is no longer seeded here.
 *  - `editorial.*` family for the editorial-pipeline signal-monitor layer
 *    (ADR-020 D4, Sprint 53 T53.4b). `editorial.pipeline.enabled` stays
 *    `false` at sprint-end — Sprint 54 flips it when the verification
 *    layer lands.
 */
class AppSettingsFixture extends Fixture implements FixtureGroupInterface
{
    /**
     * @var array<string, string>
     */
    private const ARTICLE_GENERATION_DEFAULTS = [
        // Lookback window when collecting PressReleases for a topic-driven
        // generation run. ADR-019 D7: 24h — covers the full daily news cycle
        // on Moldovan sources without bleeding into unrelated next-day stories.
        'article_generation.window_hours' => '24',

        // Eligibility floor: minimum distinct PressReleases linked to the
        // topic in window before generation is attempted. ADR-019 D1: 1
        // (singletons allowed) — 80.5% of historical clusters were singletons;
        // gating at 3 would discard the majority of valid generation targets.
        'article_generation.min_pr_count' => '1',

        // Eligibility floor: minimum average detection-confidence across
        // PressReleaseTopic links in the batch. ADR-019 D1: 2.0 floors out
        // weak topic associations (e.g. broad-keyword false positives)
        // before they reach the LLM.
        'article_generation.min_topic_relevance' => '2.0',

        // Eligibility floor: minimum average content length across the batch.
        // Preserved guardrail from the legacy ArticleWriterService constant —
        // keeps the LLM from generating articles off thin/headline-only PRs.
        'article_generation.min_avg_content_length' => '1500',
    ];

    /**
     * Editorial-pipeline AppSettings (Sprint 53 T53.4b, ADR-020 D4).
     *
     * Values are strings because `app_settings.value` is a single TEXT column
     * — consuming services cast at read time (see {@see \App\Service\AppSettingService}).
     *
     * @var array<string, string>
     */
    private const EDITORIAL_DEFAULTS = [
        // Master kill-switch for the editorial-pipeline signal collection
        // and verification layer. Stays `false` through Sprint 53 — Sprint 54
        // activates it after the L2 verification services land.
        'editorial.pipeline.enabled' => 'false',

        // WireSourceMonitor (T53.6) — covers wire_neutral, ukrainian_state,
        // independent_ru, kremlin_aligned alignments.
        'editorial.monitor.wire.enabled' => 'true',
        // 180s = 3min refresh for wire sources (fast breaking-news cycle).
        'editorial.monitor.wire.fetch_interval_seconds' => '180',

        // MediaRoSourceMonitor (T53.7) — covers md_investigative,
        // md_independent_pro_eu, md_government, ro_mainstream alignments.
        'editorial.monitor.media_ro.enabled' => 'true',
        // 300s = 5min refresh — slower than wire because MD/RO editorial
        // outlets publish on a less frenetic cadence.
        'editorial.monitor.media_ro.fetch_interval_seconds' => '300',
    ];

    public static function getGroups(): array
    {
        return ['app-settings'];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::ARTICLE_GENERATION_DEFAULTS as $key => $value) {
            $this->upsertIfMissing($manager, $key, $value);
        }

        foreach (self::EDITORIAL_DEFAULTS as $key => $value) {
            $this->upsertIfMissing($manager, $key, $value);
        }

        $manager->flush();
    }

    private function upsertIfMissing(ObjectManager $manager, string $key, string $value): void
    {
        $existing = $manager->getRepository(AppSetting::class)->find($key);
        if ($existing !== null) {
            return;
        }
        $manager->persist(new AppSetting($key, $value));
    }
}
