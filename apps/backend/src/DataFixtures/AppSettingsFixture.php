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
 * Adds the `article_generation.*` family used by the topic-window article
 * generator that supersedes StoryCluster-based generation in Sprint 52
 * (ADR-019). Defaults mirror the constants previously hard-coded in
 * ArticleWriterService and the cluster equivalents from
 * ClusteringService::cluster_temporal_window_hours.
 *
 * ────────────────────────────────────────────────────────────────────────
 * REMOVAL MANIFEST FOR T52.11 (StoryCluster hard-drop migration)
 * ────────────────────────────────────────────────────────────────────────
 * The following AppSettings keys are bound to the StoryCluster pipeline
 * and MUST be deleted from `app_settings` in the T52.11 migration after
 * the corresponding services are removed in T52.12. Each line names the
 * call site that reads the key.
 *
 *   cluster_semantic_min_confidence
 *     ↳ src/Service/Clustering/ClusteringService.php:187
 *     ↳ src/Command/ClusterCleanupCommand.php:150
 *
 *   cluster_semantic_verification_enabled
 *     ↳ src/Service/Clustering/SemanticClusterVerifier.php:33
 *
 *   cluster_mlt_min_score
 *     ↳ src/Service/Clustering/ElasticsearchClusterFinder.php:117
 *
 *   cluster_mlt_minimum_should_match
 *     ↳ src/Service/Clustering/ElasticsearchClusterFinder.php:122
 *
 *   cluster_temporal_window_hours
 *     ↳ src/Service/Clustering/ClusteringService.php:223
 *
 * Migration SQL (T52.11):
 *   DELETE FROM app_settings WHERE key LIKE 'cluster_%';
 * ────────────────────────────────────────────────────────────────────────
 */
class AppSettingsFixture extends Fixture implements FixtureGroupInterface
{
    /**
     * Default values for the article_generation.* family. Stored as strings
     * because AppSetting.value is TEXT; AppSettingRepository::get*() coerces
     * on read.
     *
     * @var array<string, string>
     */
    private const ARTICLE_GENERATION_DEFAULTS = [
        // Feature flag — default ON to use the new topic-window path.
        // Cluster fallback path remains in code through Group 2 + T52.10
        // checkpoint and is removed in T52.12 alongside this flag itself.
        'article_generation.use_topic_window' => 'true',

        // Lookback window when collecting PressReleases for a topic-driven
        // generation run. Mirrors the legacy cluster_temporal_window_hours.
        'article_generation.window_hours' => '48',

        // Eligibility floor: minimum distinct PressReleases linked to the
        // topic in window before generation is attempted. Mirrors the
        // legacy ArticleWriterService::MIN_SOURCES_FOR_AI constant.
        'article_generation.min_sources' => '3',

        // Eligibility floor: minimum average content length across the
        // batch. Mirrors ArticleWriterService::MIN_AVG_CONTENT_LENGTH.
        'article_generation.min_avg_content_length' => '1500',

        // Hard cap on PressReleases passed to the LLM in one prompt to
        // prevent runaway prompts on heavily-covered topics.
        'article_generation.max_sources' => '20',
    ];

    public static function getGroups(): array
    {
        return ['app-settings'];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::ARTICLE_GENERATION_DEFAULTS as $key => $value) {
            // Idempotent: skip if a row already exists (operator overrides
            // survive re-runs). Dev-reset drops the schema first, so on
            // fresh databases this branch never fires.
            $existing = $manager->getRepository(AppSetting::class)->find($key);
            if ($existing !== null) {
                continue;
            }
            $manager->persist(new AppSetting($key, $value));
        }

        $manager->flush();
    }
}
