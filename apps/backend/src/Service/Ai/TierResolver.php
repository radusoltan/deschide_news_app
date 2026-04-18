<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;

/**
 * Resolves the concrete {@see LlmModelTier} for a given editorial agent
 * from `agent.{id}.model_tier` AppSettings.
 *
 * Supports tier variants (e.g. `verification_gate` has both
 * `model_tier_simple` and `model_tier_conflict`) via the `$variant` argument.
 * Also exposes the per-agent fallback tier and the enabled flag so services
 * can gate LLM calls without each one re-reading AppSettings directly.
 */
final class TierResolver
{
    public function __construct(
        private readonly AppSettingRepository $settings,
    ) {}

    /**
     * Resolve the primary tier for an agent.
     *
     * @param string $agentId e.g. `source_attribution`, `signal_aggregator`, `verification_gate`, `context`
     * @param string $variant key suffix after `agent.{id}.` — defaults to `model_tier`;
     *                        verification_gate uses `model_tier_simple` / `model_tier_conflict`
     *
     * @throws \InvalidArgumentException when the setting is absent (fixture not loaded)
     * @throws \ValueError when the stored value is not a valid LlmModelTier
     */
    public function resolve(string $agentId, string $variant = 'model_tier'): LlmModelTier
    {
        $key = sprintf('agent.%s.%s', $agentId, $variant);
        $value = $this->settings->get($key);

        if ($value === null) {
            throw new \InvalidArgumentException(sprintf(
                'Missing AppSetting "%s" — run AppSettingsFixture to seed agent tiers.',
                $key,
            ));
        }

        return LlmModelTier::from($value);
    }

    /**
     * Resolve the fallback tier for an agent. Returns null when the fallback
     * setting is missing or explicitly empty string (ADR-020 D5 Tier B/C:
     * some agents intentionally have no fallback, e.g. `context`).
     */
    public function resolveFallback(string $agentId): ?LlmModelTier
    {
        $value = $this->settings->get(sprintf('agent.%s.fallback', $agentId));

        if ($value === null || $value === '') {
            return null;
        }

        return LlmModelTier::from($value);
    }

    /**
     * Whether the agent is enabled — consumers should short-circuit LLM
     * invocation when this returns false (fail-open per ADR-020).
     *
     * Defaults to true when the setting is absent, to avoid accidentally
     * disabling agents during partial fixture loads.
     */
    public function isEnabled(string $agentId): bool
    {
        return $this->settings->getBool(sprintf('agent.%s.enabled', $agentId), true);
    }
}
