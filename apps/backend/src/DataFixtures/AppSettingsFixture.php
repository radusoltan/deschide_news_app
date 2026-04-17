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
 * (ADR-019). Defaults follow ADR-019 D1 + D7 (empirically chosen against
 * historical PressRelease/cluster distributions); see commit message for
 * the T52.9 amendment for the per-key rationale.
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
     * Defaults per ADR-019 D1 + D7. Values amended in T52.9 follow-up
     * after spec divergence was caught; see commit log for audit trail.
     *
     * @var array<string, string>
     */
    private const ARTICLE_GENERATION_DEFAULTS = [
        // Feature flag — default ON to use the new topic-window path.
        // Cluster fallback path remains in code through Group 2 + T52.10
        // checkpoint and is removed in T52.12 alongside this flag itself.
        'article_generation.use_topic_window' => 'true',

        // Lookback window when collecting PressReleases for a topic-driven
        // generation run. ADR-019 D7: 24h chosen empirically — covers the
        // full daily news cycle on Moldovan sources without bleeding into
        // unrelated next-day stories.
        'article_generation.window_hours' => '24',

        // Eligibility floor: minimum distinct PressReleases linked to the
        // topic in window before generation is attempted. ADR-019 D1:
        // 1 (singletons allowed) — 80.5% of historical clusters were
        // singletons; gating at 3 would discard the majority of valid
        // generation targets.
        'article_generation.min_pr_count' => '1',

        // Eligibility floor: minimum average detection-confidence across
        // PressReleaseTopic links in the batch. ADR-019 D1: 2.0 floors
        // out weak topic associations (e.g. broad-keyword false positives)
        // before they reach the LLM.
        'article_generation.min_topic_relevance' => '2.0',

        // Eligibility floor: minimum average content length across the
        // batch. Preserved guardrail from the legacy
        // ArticleWriterService::MIN_AVG_CONTENT_LENGTH constant — keeps
        // the LLM from generating articles off thin/headline-only PRs.
        // ADR-019 amendment will document this as a preserved-from-legacy
        // decision rather than an empirical re-derivation.
        'article_generation.min_avg_content_length' => '1500',
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
