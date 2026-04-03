<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai\Provider;

use App\Enum\AiAgentType;
use App\Service\Ai\AnthropicClientInterface;
use App\Service\Ai\Provider\AnthropicProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AnthropicProviderTest extends TestCase
{
    private AnthropicProvider $provider;
    private AnthropicClientInterface $client;

    protected function setUp(): void
    {
        $this->client = $this->createStub(AnthropicClientInterface::class);
        $this->client->method('chat')->willReturn('Test response');

        $this->provider = new AnthropicProvider($this->client, new NullLogger());
    }

    public function testGetNameReturnsAnthropic(): void
    {
        $this->assertSame('anthropic', $this->provider->getName());
    }

    public function testSupportsVault(): void
    {
        $this->assertTrue($this->provider->supports(AiAgentType::VAULT));
    }

    public function testSupportsContent(): void
    {
        $this->assertTrue($this->provider->supports(AiAgentType::CONTENT));
    }

    public function testSupportsBriefing(): void
    {
        $this->assertTrue($this->provider->supports(AiAgentType::BRIEFING));
    }

    public function testNotSupportsTranslation(): void
    {
        $this->assertFalse($this->provider->supports(AiAgentType::TRANSLATION));
    }

    public function testNotSupportsSeo(): void
    {
        $this->assertFalse($this->provider->supports(AiAgentType::SEO));
    }

    public function testGetModelForContent(): void
    {
        $this->assertSame('claude-sonnet-4-20250514', $this->provider->getModelForAgent(AiAgentType::CONTENT));
    }

    public function testGetModelForVault(): void
    {
        $this->assertSame('claude-haiku-4-5-20251001', $this->provider->getModelForAgent(AiAgentType::VAULT));
    }

    public function testGetModelForBriefing(): void
    {
        $this->assertSame('claude-haiku-4-5-20251001', $this->provider->getModelForAgent(AiAgentType::BRIEFING));
    }

    public function testChatDelegatesToClient(): void
    {
        $result = $this->provider->chat('Hello', 'System prompt');
        $this->assertSame('Test response', $result);
    }
}
