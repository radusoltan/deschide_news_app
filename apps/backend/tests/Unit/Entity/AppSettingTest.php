<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AppSetting;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the {@see AppSetting::CRITICAL_KEYS} allow-list and
 * {@see AppSetting::isCriticalKey()} glob matcher.
 */
class AppSettingTest extends TestCase
{
    public function testExactMatchEmergencyHalt(): void
    {
        $this->assertTrue(AppSetting::isCriticalKey('agent.emergency_halt'));
    }

    public function testNonCriticalKeyReturnsFalse(): void
    {
        $this->assertFalse(AppSetting::isCriticalKey('agent.flash_writer.enabled'));
        $this->assertFalse(AppSetting::isCriticalKey('random.unknown.key'));
        $this->assertFalse(AppSetting::isCriticalKey(''));
    }

    public function testCriticalKeysListContainsExpectedEntries(): void
    {
        $this->assertSame(
            ['agent.emergency_halt'],
            AppSetting::CRITICAL_KEYS,
        );
    }
}
