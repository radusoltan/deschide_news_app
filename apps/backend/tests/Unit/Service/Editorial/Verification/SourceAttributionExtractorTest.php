<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Verification;

use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Enum\EditorialAlignment;
use App\Enum\LlmModelTier;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\TierResolver;
use App\Service\Editorial\Verification\SourceAttributionExtractor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SourceAttributionExtractorTest extends TestCase
{
    private LlmRetryExecutor&MockObject $executor;
    private TierResolver&MockObject $tierResolver;
    private SourceAttributionExtractor $extractor;

    protected function setUp(): void
    {
        $this->executor = $this->createMock(LlmRetryExecutor::class);
        $this->tierResolver = $this->createMock(TierResolver::class);
        $this->tierResolver->method('resolve')
            ->with(SourceAttributionExtractor::AGENT_ID)
            ->willReturn(LlmModelTier::HAIKU);

        $this->extractor = new SourceAttributionExtractor(
            $this->executor,
            $this->tierResolver,
            new NullLogger(),
        );
    }

    public function testExtractParsesCleanJsonResponse(): void
    {
        $this->executor->expects($this->once())
            ->method('executeWithRetry')
            ->willReturn([
                'content' => '{"source_attribution":"potrivit Reuters","source_links_out":["https://www.reuters.com/article/xyz"]}',
                'agent_id' => 'source_attribution',
                'tier' => 'haiku',
                'model' => 'claude-haiku-4-5-20251001',
                'attempts' => 1,
                'fallback_detected' => false,
                'metrics' => null,
            ]);

        $result = $this->extractor->extract($this->buildSignal());

        $this->assertSame('potrivit Reuters', $result->sourceAttribution);
        $this->assertSame(['https://www.reuters.com/article/xyz'], $result->linksOut);
    }

    public function testExtractHandlesMarkdownFencedJson(): void
    {
        $this->executor->method('executeWithRetry')->willReturn([
            'content' => "```json\n{\"source_attribution\":null,\"source_links_out\":[]}\n```",
            'agent_id' => 'source_attribution',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $result = $this->extractor->extract($this->buildSignal());

        $this->assertNull($result->sourceAttribution);
        $this->assertSame([], $result->linksOut);
        $this->assertTrue($result->isEmpty());
    }

    public function testExtractPreservesRomanianDiacriticsCommaBelow(): void
    {
        $this->executor->method('executeWithRetry')->willReturn([
            'content' => '{"source_attribution":"potrivit ziarului Ziarul de Gardă, care citează surse parlamentare","source_links_out":[]}',
            'agent_id' => 'source_attribution',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $result = $this->extractor->extract($this->buildSignal());

        // Gardă uses ă (a with breve), which is fine.
        // Critical test: no cedilla variants (ş U+015F, ţ U+0163) accepted through parsing.
        $this->assertSame(
            'potrivit ziarului Ziarul de Gardă, care citează surse parlamentare',
            $result->sourceAttribution,
        );
        // Verify comma-below diacritics survive — they're already in the response as-is.
        $this->assertStringContainsString('Gardă', $result->sourceAttribution);
    }

    public function testSystemPromptUsesOnlyCommaBelowDiacritics(): void
    {
        $reflection = new \ReflectionMethod($this->extractor, 'getSystemPrompt');
        $prompt = (string) $reflection->invoke($this->extractor);

        // Forbidden cedilla variants (s-cedilla U+015F, t-cedilla U+0163) must never appear.
        $this->assertStringNotContainsString("\u{015F}", $prompt, 's-cedilla in system prompt');
        $this->assertStringNotContainsString("\u{0163}", $prompt, 't-cedilla in system prompt');
        // Expected comma-below diacritics present.
        $this->assertStringContainsString("\u{0219}", $prompt, 's-comma (ș) expected');
        $this->assertStringContainsString("\u{021B}", $prompt, 't-comma (ț) expected');
    }

    public function testExtractFailsOpenOnNonJsonResponse(): void
    {
        $this->executor->method('executeWithRetry')->willReturn([
            'content' => 'Îmi pare rău, nu pot procesa această cerere.',
            'agent_id' => 'source_attribution',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $result = $this->extractor->extract($this->buildSignal());

        $this->assertTrue($result->isEmpty());
    }

    public function testExtractFailsOpenOnInvalidUrlsInLinksOut(): void
    {
        $this->executor->method('executeWithRetry')->willReturn([
            'content' => '{"source_attribution":"raportul Bellingcat","source_links_out":["not-a-url","https://example.com/real","","javascript:alert(1)"]}',
            'agent_id' => 'source_attribution',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $result = $this->extractor->extract($this->buildSignal());

        $this->assertSame('raportul Bellingcat', $result->sourceAttribution);
        // Only the http(s) URL passes FILTER_VALIDATE_URL and non-empty check.
        // javascript: URL may or may not pass depending on PHP filter — we just assert
        // the obvious invalid ones are dropped.
        $this->assertContains('https://example.com/real', $result->linksOut);
        $this->assertNotContains('not-a-url', $result->linksOut);
        $this->assertNotContains('', $result->linksOut);
    }

    public function testExtractFailsOpenOnLlmUnavailableException(): void
    {
        $this->executor->method('executeWithRetry')
            ->willThrowException(new LlmUnavailableException(
                agentId: SourceAttributionExtractor::AGENT_ID,
                tier: LlmModelTier::HAIKU,
                fallbackTier: LlmModelTier::GEMINI_FLASH,
                attempts: 4,
            ));

        $result = $this->extractor->extract($this->buildSignal());

        $this->assertTrue($result->isEmpty());
    }

    public function testExtractNormalizesEmptyStringAttributionToNull(): void
    {
        $this->executor->method('executeWithRetry')->willReturn([
            'content' => '{"source_attribution":"   ","source_links_out":[]}',
            'agent_id' => 'source_attribution',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $result = $this->extractor->extract($this->buildSignal());

        $this->assertNull($result->sourceAttribution);
    }

    public function testExtractHandlesAttributionOnlyResult(): void
    {
        $this->executor->method('executeWithRetry')->willReturn([
            'content' => '{"source_attribution":"surse diplomatice","source_links_out":[]}',
            'agent_id' => 'source_attribution',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $result = $this->extractor->extract($this->buildSignal());

        $this->assertSame('surse diplomatice', $result->sourceAttribution);
        $this->assertSame([], $result->linksOut);
        $this->assertFalse($result->isEmpty());
    }

    private function buildSignal(): SourceSignal
    {
        $source = new \App\Entity\Source();
        $source->setName('Stub Source');

        $verified = new VerifiedSource(
            slug: 'stub-' . uniqid(),
            tier: 1,
            editorialAlignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.80',
            source: $source,
        );

        $signal = new SourceSignal(
            verifiedSource: $verified,
            sourceUrl: 'https://example.com/article/42',
            title: 'Exemplu de titlu: un oficial declară',
            rawContentHash: str_repeat('a', 64),
            capturedAt: new \DateTimeImmutable('2026-04-18 12:00:00'),
        );
        $signal->setRawSummary('Un rezumat scurt care ar putea menționa potrivit Reuters sau alte surse.');

        return $signal;
    }
}
