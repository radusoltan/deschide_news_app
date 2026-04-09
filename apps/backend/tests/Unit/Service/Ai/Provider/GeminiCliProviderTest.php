<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai\Provider;

use App\Enum\AiAgentType;
use App\Service\Ai\Provider\GeminiCliProvider;
use App\Service\Ai\Provider\GeminiCliService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GeminiCliProviderTest extends TestCase
{
    private GeminiCliProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new GeminiCliProvider(new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()));
    }

    public function testGetNameReturnsGemini(): void
    {
        $this->assertSame('gemini', $this->provider->getName());
    }

    public function testSupportsTranslation(): void
    {
        $this->assertTrue($this->provider->supports(AiAgentType::TRANSLATION));
    }

    public function testSupportsSeo(): void
    {
        $this->assertTrue($this->provider->supports(AiAgentType::SEO));
    }

    public function testNotSupportsContent(): void
    {
        $this->assertFalse($this->provider->supports(AiAgentType::CONTENT));
    }

    public function testNotSupportsVault(): void
    {
        $this->assertFalse($this->provider->supports(AiAgentType::RESEARCH));
    }

    public function testNotSupportsBriefing(): void
    {
        $this->assertFalse($this->provider->supports(AiAgentType::BRIEFING));
    }

    public function testGetModelForTranslation(): void
    {
        $this->assertSame('gemini-2.5-flash', $this->provider->getModelForAgent(AiAgentType::TRANSLATION));
    }

    public function testGetModelForSeo(): void
    {
        $this->assertSame('gemini-2.5-flash', $this->provider->getModelForAgent(AiAgentType::SEO));
    }

    public function testChatThrowsOnInvalidPath(): void
    {
        $provider = new GeminiCliProvider(new GeminiCliService('/nonexistent/gemini', '/tmp', new NullLogger()));

        $this->expectException(\RuntimeException::class);

        $provider->chat('Test prompt');
    }
}
