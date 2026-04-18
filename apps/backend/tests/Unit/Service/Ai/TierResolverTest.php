<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai;

use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\TierResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TierResolverTest extends TestCase
{
    private AppSettingRepository&MockObject $settings;
    private TierResolver $resolver;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->resolver = new TierResolver($this->settings);
    }

    public function testResolveReturnsTierFromAppSettings(): void
    {
        $this->settings->expects($this->once())
            ->method('get')
            ->with('agent.source_attribution.model_tier')
            ->willReturn('haiku');

        $this->assertSame(
            LlmModelTier::HAIKU,
            $this->resolver->resolve('source_attribution'),
        );
    }

    public function testResolveWithVariantUsesSuffixedKey(): void
    {
        $this->settings->expects($this->once())
            ->method('get')
            ->with('agent.verification_gate.model_tier_conflict')
            ->willReturn('sonnet');

        $tier = $this->resolver->resolve('verification_gate', 'model_tier_conflict');

        $this->assertSame(LlmModelTier::SONNET, $tier);
    }

    public function testResolveThrowsWhenSettingMissing(): void
    {
        $this->settings->method('get')
            ->with('agent.unknown_agent.model_tier')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Missing AppSetting.*agent\.unknown_agent\.model_tier/');

        $this->resolver->resolve('unknown_agent');
    }

    public function testResolveThrowsOnInvalidTierValue(): void
    {
        $this->settings->method('get')
            ->willReturn('not_a_real_tier');

        $this->expectException(\ValueError::class);

        $this->resolver->resolve('signal_aggregator');
    }

    public function testResolveFallbackReturnsTier(): void
    {
        $this->settings->expects($this->once())
            ->method('get')
            ->with('agent.source_attribution.fallback')
            ->willReturn('gemini_flash');

        $this->assertSame(
            LlmModelTier::GEMINI_FLASH,
            $this->resolver->resolveFallback('source_attribution'),
        );
    }

    public function testResolveFallbackReturnsNullOnEmptyString(): void
    {
        $this->settings->method('get')
            ->with('agent.context.fallback')
            ->willReturn('');

        $this->assertNull($this->resolver->resolveFallback('context'));
    }

    public function testResolveFallbackReturnsNullWhenMissing(): void
    {
        $this->settings->method('get')
            ->with('agent.unknown.fallback')
            ->willReturn(null);

        $this->assertNull($this->resolver->resolveFallback('unknown'));
    }

    public function testIsEnabledDefaultsTrueWhenMissing(): void
    {
        $this->settings->expects($this->once())
            ->method('getBool')
            ->with('agent.source_attribution.enabled', true)
            ->willReturn(true);

        $this->assertTrue($this->resolver->isEnabled('source_attribution'));
    }

    public function testIsEnabledReturnsFalseWhenDisabled(): void
    {
        $this->settings->method('getBool')
            ->with('agent.signal_aggregator.enabled', true)
            ->willReturn(false);

        $this->assertFalse($this->resolver->isEnabled('signal_aggregator'));
    }
}
