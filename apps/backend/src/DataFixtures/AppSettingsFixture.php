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
     * — consuming services cast at read time (see {@see \App\Repository\AppSettingRepository::getBool()}
     * and related typed accessors).
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

        // MediaRuSourceMonitor (Sprint 54 T54.5) — covers independent_ru,
        // kremlin_aligned alignments. Overlaps with WireSourceMonitor on
        // these two alignments by design; the independent cadence (300s)
        // gives Russian-language coverage its own operational signal.
        'editorial.monitor.media_ru.enabled' => 'true',
        'editorial.monitor.media_ru.fetch_interval_seconds' => '300',

        // SignalStabilizationBuffer window (Sprint 54 T54.6, ADR-020 D2).
        // A signal must sit in the Redis buffer for at least this many
        // seconds before it's considered stabilized and eligible for
        // clustering. 45s = tuned for the typical "wire first, others follow"
        // cadence observed in MD/RO news cycles (Sprint 53 manual analysis).
        'editorial.stabilization_window_seconds' => '45',

        // SignalAggregator thresholds (Sprint 54 T54.8, ADR-020 D3).
        //
        // - min_es_score: Elasticsearch `more_like_this` min_score used when
        //   finding similar articles for a given signal. 0.65 is the value
        //   the existing Sprint 48 cluster-verification service tuned for
        //   the trilingual index; reuse keeps behaviour aligned.
        // - min_cluster_overlap: number of shared ES-matched article ids
        //   between two signals required to place them in the same
        //   candidate cluster. 2 = at least two common references before
        //   we hand a pair to the LLM semantic gate.
        // - llm_gate_confidence_threshold: Haiku must return is_same_claim
        //   + confidence >= this value for a multi-signal cluster to be
        //   confirmed. Single-signal clusters bypass the gate.
        'editorial.aggregator.min_es_score' => '0.65',
        'editorial.aggregator.min_cluster_overlap' => '2',
        'editorial.aggregator.llm_gate_confidence_threshold' => '0.7',

        // VerificationGate LLM-override floor (Sprint 54 T54.9, ADR-020 D3).
        // The rule-based D3 verdict only yields to the LLM's alternative
        // verdict when the LLM's own confidence is at or above this floor.
        // 0.8 keeps the override mechanism conservative — rule-based decisions
        // remain the default path.
        'editorial.verification.llm_override_confidence' => '0.8',

        // NotebookLM fact-check hook (Sprint 54 T54.10). Off by default —
        // flipping this on emits a structured `verification_notebooklm_would_invoke`
        // log line every time the VerificationGate lands a high-stakes
        // verdict (ESCALATE_HUMAN or FLASH_WITH_ASSERTION_YELLOW with a
        // high-stakes keyword match). Actual NotebookLM invocation is
        // deferred to Sprint 55 where Article/Topic context plumbing lands.
        'notebooklm.factcheck.enabled' => 'false',
    ];

    /**
     * Per-agent LLM tier AppSettings (Sprint 54 T54.1, ADR-020 D5).
     *
     * Each editorial agent declares three keys:
     *   - `agent.{id}.model_tier` (or variants like `model_tier_simple` /
     *     `model_tier_conflict` for verification_gate): primary {@see \App\Enum\LlmModelTier} value
     *   - `agent.{id}.fallback`: secondary tier used by LlmRetryExecutor when
     *     primary exhausts; empty string means "no fallback" (Tier B/C in ADR-020 D5)
     *   - `agent.{id}.enabled`: gate — false skips the LLM call entirely
     *
     * Resolved through {@see \App\Service\Ai\TierResolver}. The pipeline
     * master switch (`editorial.pipeline.enabled`) overrides these; an
     * individual agent can still be toggled off without killing the whole
     * pipeline.
     *
     * @var array<string, string>
     */
    private const AGENT_TIER_DEFAULTS = [
        // SourceAttributionExtractor (T54.7) — extracts `source_attribution`
        // and `source_links_out` from each signal. Cheap per-signal call,
        // Haiku is sufficient; Gemini fallback keeps extraction flowing if
        // Anthropic is unavailable.
        'agent.source_attribution.model_tier' => 'haiku',
        'agent.source_attribution.fallback' => 'gemini_flash',
        'agent.source_attribution.enabled' => 'true',

        // SignalAggregator (T54.8) — clusters signals + runs LLM semantic
        // gate on each candidate cluster. Haiku primary, Gemini fallback.
        'agent.signal_aggregator.model_tier' => 'haiku',
        'agent.signal_aggregator.fallback' => 'gemini_flash',
        'agent.signal_aggregator.enabled' => 'true',

        // VerificationGate (T54.9) — D3 publication matrix. Two variants:
        // simple (single-chain / unambiguous diverse) runs on Haiku;
        // conflict (contradictory claims across chains) escalates to Sonnet.
        'agent.verification_gate.model_tier_simple' => 'haiku',
        'agent.verification_gate.model_tier_conflict' => 'sonnet',
        'agent.verification_gate.fallback' => 'gemini_flash',
        'agent.verification_gate.enabled' => 'true',

        // ContextAgent (T54.11) — Elasticsearch MLT + Sonnet narrative
        // synthesis. No fallback per ADR-020 D5 Tier B: Gemini produces
        // lower-quality narrative and we'd rather return isNovelClaim=true
        // than emit weak context.
        'agent.context.model_tier' => 'sonnet',
        'agent.context.fallback' => '',
        'agent.context.enabled' => 'true',
    ];

    /**
     * Writer / Guard / Escalation / Throttle AppSettings (Sprint 55 T55.14).
     *
     * Seed for the L3 writers, L4 guards, EscalationClassifier and global
     * pipeline throttle landing in S55. Longform synthesizer and headline
     * optimizer ship with `enabled=false` per orchestrator D14/D17 — their
     * implementation slips to S56. SLA values follow D9/D21 (day window
     * 07:00-22:00 Europe/Chișinău, 10 min day / 30 min night).
     *
     * @var array<string, string>
     */
    private const WRITER_GUARD_ESCALATION_DEFAULTS = [
        // Writers (T55.3, T55.4) — Haiku primary, Gemini Flash fallback (direct
        // GeminiCliService call, bypassing LlmRetryExecutor per audit hard rule 6).
        'agent.flash_writer.model_tier' => 'haiku',
        'agent.flash_writer.fallback' => 'gemini_flash',
        'agent.flash_writer.enabled' => 'true',
        'agent.developing_story_writer.model_tier' => 'haiku',
        'agent.developing_story_writer.fallback' => 'gemini_flash',
        'agent.developing_story_writer.enabled' => 'true',

        // LongformSynthesizer (S56 — seed disabled). Sonnet-only per Tier B
        // guidance (no Gemini fallback for long narrative synthesis).
        'agent.longform_synthesizer.model_tier' => 'sonnet',
        'agent.longform_synthesizer.fallback' => '',
        'agent.longform_synthesizer.enabled' => 'false',

        // HeadlineOptimizer (S56 — seed disabled per audit D14). Sonnet-primary
        // for editorial polish, Gemini fallback for bulk processing.
        'agent.headline_optimizer.model_tier' => 'sonnet',
        'agent.headline_optimizer.fallback' => 'gemini_flash',
        'agent.headline_optimizer.enabled' => 'false',

        // Guards (T55.6, T55.7). LegalGuard splits by category: general cases
        // run on Haiku; Category 6 (personalised criminal accusations per
        // ADR-020 D7) escalates to Sonnet for nuance.
        'agent.style_guard.model_tier' => 'haiku',
        'agent.style_guard.fallback' => 'gemini_flash',
        'agent.style_guard.enabled' => 'true',
        'agent.legal_guard.model_tier_general' => 'haiku',
        'agent.legal_guard.model_tier_categ6' => 'sonnet',
        'agent.legal_guard.fallback' => 'gemini_flash',
        'agent.legal_guard.enabled' => 'true',

        // EscalationClassifier (T55.8) — Haiku with Gemini fallback. Fail-open:
        // classifier null result does not block the pipeline; handler uses a
        // generic family default and logs the gap.
        'agent.escalation_classifier.model_tier' => 'haiku',
        'agent.escalation_classifier.fallback' => 'gemini_flash',
        'agent.escalation_classifier.enabled' => 'true',

        // SLA window + expiry (T55.8, T55.11). Day/night bands mirror the
        // Europe/Chișinău editorial rhythm: tighter SLA during business hours,
        // relaxed SLA overnight. Role taxonomy version pins the ADR-020 D7
        // prompt seed — increments when Radu updates the role set.
        'editorial.escalation.sla_day_seconds' => '600',
        'editorial.escalation.sla_night_seconds' => '1800',
        'editorial.escalation.sla_day_start_hour' => '7',
        'editorial.escalation.sla_day_end_hour' => '22',
        'editorial.escalation.role_taxonomy_version' => 'v1',

        // Global writer throttle (audit D16). Smoke ceiling is 3/h — T55.17
        // sets it to 3 explicitly before the smoke window and restores to 10.
        'editorial.throttle.articles_per_hour' => '10',
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

        foreach (self::AGENT_TIER_DEFAULTS as $key => $value) {
            $this->upsertIfMissing($manager, $key, $value);
        }

        foreach (self::WRITER_GUARD_ESCALATION_DEFAULTS as $key => $value) {
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
