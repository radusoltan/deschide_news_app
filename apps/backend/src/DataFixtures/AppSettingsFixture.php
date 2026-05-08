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
 * Post-T60.X-NUKE-EDITORIAL: editorial pipelines were demolished, only the
 * translation pipeline + the agent emergency-halt circuit breaker remain.
 */
class AppSettingsFixture extends Fixture implements FixtureGroupInterface
{
    /**
     * @var array<string, string>
     */
    private const DEFAULTS = [
        // Emergency circuit breaker for agent dispatch (renamed from
        // `editorial.emergency_halt` in T60.X). Flipping to `true` causes
        // AgentDispatcher to throw EmergencyHaltException at dispatch entry,
        // BEFORE any LLM call. Sole entry in AppSetting::CRITICAL_KEYS — flips
        // require operator-supplied `--reason` via `app:settings:set`.
        'agent.emergency_halt' => 'false',

        // Translation agent (T57.P7) — the only LLM agent retained on Gemini
        // CLI post-T60.X. Quality-confirmed for RO↔EN↔RU. Timeout headroom
        // accommodates long-form articles + per-locale call pattern that
        // avoids the Gemini 64KB output ceiling.
        // Note: `agent.journalistic_translator.enabled` was dropped post-review —
        // had zero consumers (TierResolver::isEnabled() never wired to the dispatch
        // path). Re-introduce only when there's a code path that reads it.
        'agent.journalistic_translator.model_tier' => 'gemini_flash',
        'agent.journalistic_translator.timeout_seconds' => '300',
    ];

    public static function getGroups(): array
    {
        return ['app-settings'];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::DEFAULTS as $key => $value) {
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
