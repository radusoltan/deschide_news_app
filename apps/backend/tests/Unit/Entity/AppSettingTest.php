<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AppSetting;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the T57.P3 {@see AppSetting::CRITICAL_KEYS} allow-list and
 * {@see AppSetting::isCriticalKey()} glob matcher (ADR-024 D5).
 *
 * Matcher correctness is load-bearing: a false negative lets an operator flip
 * `editorial.emergency_halt` with no `--reason` and no audit context; a false
 * positive forces a reason on a benign key and breaks non-critical flip flows.
 */
class AppSettingTest extends TestCase
{
    public function testExactMatchEmergencyHalt(): void
    {
        $this->assertTrue(AppSetting::isCriticalKey('editorial.emergency_halt'));
    }

    public function testExactMatchPipelineEnabled(): void
    {
        $this->assertTrue(AppSetting::isCriticalKey('editorial.pipeline.enabled'));
    }

    public function testGlobMatchTierOverridesAgent(): void
    {
        // Per-agent tier override is the primary CRITICAL_KEYS wildcard case.
        $this->assertTrue(AppSetting::isCriticalKey('editorial.tier_overrides.flash_writer'));
        $this->assertTrue(AppSetting::isCriticalKey('editorial.tier_overrides.developing_story_writer'));
        $this->assertTrue(AppSetting::isCriticalKey('editorial.tier_overrides.legal_guard'));
    }

    public function testGlobMatchTierOverridesMultiSegment(): void
    {
        // fnmatch `*` matches runs of characters including dots — a nested
        // key like `editorial.tier_overrides.foo.bar` still reads as critical.
        $this->assertTrue(AppSetting::isCriticalKey('editorial.tier_overrides.foo.bar'));
    }

    public function testBarePrefixWithoutTrailingSegmentDoesNotMatch(): void
    {
        // `editorial.tier_overrides` with no trailing segment must NOT match
        // `editorial.tier_overrides.*` — the wildcard requires at least one char.
        $this->assertFalse(AppSetting::isCriticalKey('editorial.tier_overrides'));
    }

    public function testNonCriticalKeyReturnsFalse(): void
    {
        // Regular operational toggles not in the allow-list must not require
        // `--reason` enforcement.
        $this->assertFalse(AppSetting::isCriticalKey('article_generation.window_hours'));
        $this->assertFalse(AppSetting::isCriticalKey('agent.flash_writer.enabled'));
        $this->assertFalse(AppSetting::isCriticalKey('random.unknown.key'));
        $this->assertFalse(AppSetting::isCriticalKey(''));
    }

    public function testCriticalKeysListContainsExpectedEntries(): void
    {
        // Pin the public constant so a silent removal of a critical entry would
        // surface as a red test (e.g. an ADR-024 revision that drops a key).
        $this->assertSame(
            [
                'editorial.emergency_halt',
                'editorial.pipeline.enabled',
                'editorial.tier_overrides.*',
                'briefing.llm.use_legacy_gemini_*',
            ],
            AppSetting::CRITICAL_KEYS,
        );
    }

    public function testGlobMatchBriefingLegacyGeminiFlags(): void
    {
        // T57.P4+P5 — per-cadence legacy rollback flags carry production blast
        // radius equivalent to editorial.tier_overrides.*; flips require
        // operator-supplied `--reason` via app:settings:set.
        $this->assertTrue(AppSetting::isCriticalKey('briefing.llm.use_legacy_gemini_daily'));
        $this->assertTrue(AppSetting::isCriticalKey('briefing.llm.use_legacy_gemini_hourly'));
        $this->assertTrue(AppSetting::isCriticalKey('briefing.llm.use_legacy_gemini_weekly'));
    }
}
