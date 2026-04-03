<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai\Provider;

use App\Enum\AiAgentType;
use App\Service\Ai\Provider\AiProviderInterface;
use App\Service\Ai\Provider\AiProviderRegistry;
use PHPUnit\Framework\TestCase;

class AiProviderRegistryTest extends TestCase
{
    private AiProviderInterface $anthropicProvider;
    private AiProviderInterface $geminiProvider;
    private AiProviderRegistry $registry;

    protected function setUp(): void
    {
        $this->anthropicProvider = $this->createStub(AiProviderInterface::class);
        $this->anthropicProvider->method('getName')->willReturn('anthropic');
        $this->anthropicProvider->method('supports')->willReturnCallback(
            fn (AiAgentType $type) => \in_array($type, [
                AiAgentType::VAULT,
                AiAgentType::CONTENT,
                AiAgentType::BRIEFING,
            ], true),
        );

        $this->geminiProvider = $this->createStub(AiProviderInterface::class);
        $this->geminiProvider->method('getName')->willReturn('gemini');
        $this->geminiProvider->method('supports')->willReturnCallback(
            fn (AiAgentType $type) => \in_array($type, [
                AiAgentType::TRANSLATION,
                AiAgentType::SEO,
            ], true),
        );

        $this->registry = new AiProviderRegistry([$this->anthropicProvider, $this->geminiProvider]);
    }

    public function testReturnsAnthropicForVault(): void
    {
        $provider = $this->registry->getProvider(AiAgentType::VAULT);
        $this->assertSame('anthropic', $provider->getName());
    }

    public function testReturnsAnthropicForContent(): void
    {
        $provider = $this->registry->getProvider(AiAgentType::CONTENT);
        $this->assertSame('anthropic', $provider->getName());
    }

    public function testReturnsAnthropicForBriefing(): void
    {
        $provider = $this->registry->getProvider(AiAgentType::BRIEFING);
        $this->assertSame('anthropic', $provider->getName());
    }

    public function testReturnsGeminiForTranslation(): void
    {
        $provider = $this->registry->getProvider(AiAgentType::TRANSLATION);
        $this->assertSame('gemini', $provider->getName());
    }

    public function testReturnsGeminiForSeo(): void
    {
        $provider = $this->registry->getProvider(AiAgentType::SEO);
        $this->assertSame('gemini', $provider->getName());
    }

    public function testThrowsForNoMatchingProvider(): void
    {
        // Empty registry
        $emptyRegistry = new AiProviderRegistry([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No AI provider registered for agent type "vault"');

        $emptyRegistry->getProvider(AiAgentType::VAULT);
    }
}
