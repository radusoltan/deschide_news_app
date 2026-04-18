<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai;

use App\Enum\LlmModelTier;
use App\Service\Ai\AnthropicClientInterface;
use App\Service\Ai\Exception\ClaudeCliPermanentException;
use App\Service\Ai\Exception\ClaudeCliTransientException;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\TierResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class LlmRetryExecutorTest extends TestCase
{
    private AnthropicClientInterface&MockObject $client;
    private TierResolver&MockObject $tierResolver;
    private LlmRetryExecutor $executor;

    protected function setUp(): void
    {
        $this->client = $this->createMock(AnthropicClientInterface::class);
        $this->tierResolver = $this->createMock(TierResolver::class);
        $this->executor = new LlmRetryExecutor(
            $this->client,
            $this->tierResolver,
            new NullLogger(),
            [0, 0, 0],
        );
    }

    public function testHappyPathReturnsFirstAttemptResult(): void
    {
        $this->client->expects($this->once())
            ->method('chat')
            ->with(
                [['role' => 'user', 'content' => 'hi']],
                'claude-haiku-4-5-20251001',
                null,
            )
            ->willReturn('hello');

        $result = $this->executor->executeWithRetry(
            'source_attribution',
            [['role' => 'user', 'content' => 'hi']],
            LlmModelTier::HAIKU,
        );

        $this->assertSame('hello', $result['content']);
        $this->assertSame('source_attribution', $result['agent_id']);
        $this->assertSame('haiku', $result['tier']);
        $this->assertSame('claude-haiku-4-5-20251001', $result['model']);
        $this->assertSame(1, $result['attempts']);
        $this->assertFalse($result['fallback_detected']);
    }

    public function testSystemPromptIsForwarded(): void
    {
        $this->client->expects($this->once())
            ->method('chat')
            ->with(
                [['role' => 'user', 'content' => 'hi']],
                'claude-haiku-4-5-20251001',
                'system-text',
            )
            ->willReturn('ok');

        $this->executor->executeWithRetry(
            'signal_aggregator',
            [['role' => 'user', 'content' => 'hi']],
            LlmModelTier::HAIKU,
            'system-text',
        );
    }

    public function testRetryTwiceThenSucceed(): void
    {
        $this->client->expects($this->exactly(3))
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new ClaudeCliTransientException('529 overloaded')),
                $this->throwException(new ClaudeCliTransientException('rate_limit')),
                'success',
            );

        $result = $this->executor->executeWithRetry(
            'signal_aggregator',
            [['role' => 'user', 'content' => 'x']],
            LlmModelTier::HAIKU,
        );

        $this->assertSame('success', $result['content']);
        $this->assertSame(3, $result['attempts']);
        $this->assertFalse($result['fallback_detected']);
    }

    public function testProcessTimeoutIsTreatedAsTransient(): void
    {
        $timeout = new ProcessTimedOutException(
            new Process(['true']),
            ProcessTimedOutException::TYPE_GENERAL,
        );

        $this->client->expects($this->exactly(2))
            ->method('chat')
            ->willReturnOnConsecutiveCalls(
                $this->throwException($timeout),
                'recovered',
            );

        $result = $this->executor->executeWithRetry(
            'source_attribution',
            [['role' => 'user', 'content' => 'x']],
            LlmModelTier::HAIKU,
        );

        $this->assertSame('recovered', $result['content']);
        $this->assertSame(2, $result['attempts']);
    }

    public function testExhaustedRetriesWithFallbackThrowsLlmUnavailable(): void
    {
        $this->client->expects($this->exactly(4))
            ->method('chat')
            ->willThrowException(new ClaudeCliTransientException('529'));

        $this->tierResolver->expects($this->once())
            ->method('resolveFallback')
            ->with('source_attribution')
            ->willReturn(LlmModelTier::GEMINI_FLASH);

        try {
            $this->executor->executeWithRetry(
                'source_attribution',
                [['role' => 'user', 'content' => 'x']],
                LlmModelTier::HAIKU,
            );
            $this->fail('Expected LlmUnavailableException');
        } catch (LlmUnavailableException $e) {
            $this->assertSame('source_attribution', $e->agentId);
            $this->assertSame(LlmModelTier::HAIKU, $e->tier);
            $this->assertSame(LlmModelTier::GEMINI_FLASH, $e->fallbackTier);
            $this->assertSame(4, $e->attempts);
            $this->assertTrue($e->isFallbackDetected());
            $this->assertInstanceOf(ClaudeCliTransientException::class, $e->getPrevious());
        }
    }

    public function testExhaustedRetriesWithoutFallbackStillThrowsButFallbackNotDetected(): void
    {
        $this->client->expects($this->exactly(4))
            ->method('chat')
            ->willThrowException(new ClaudeCliTransientException('rate_limit'));

        $this->tierResolver->expects($this->once())
            ->method('resolveFallback')
            ->with('context')
            ->willReturn(null);

        try {
            $this->executor->executeWithRetry(
                'context',
                [['role' => 'user', 'content' => 'x']],
                LlmModelTier::SONNET,
            );
            $this->fail('Expected LlmUnavailableException');
        } catch (LlmUnavailableException $e) {
            $this->assertSame('context', $e->agentId);
            $this->assertNull($e->fallbackTier);
            $this->assertFalse($e->isFallbackDetected());
        }
    }

    public function testPermanentExceptionThrowsImmediatelyWithNoRetry(): void
    {
        $this->client->expects($this->once())
            ->method('chat')
            ->willThrowException(new ClaudeCliPermanentException('invalid_api_key'));

        $this->tierResolver->expects($this->never())
            ->method('resolveFallback');

        $this->expectException(ClaudeCliPermanentException::class);

        $this->executor->executeWithRetry(
            'source_attribution',
            [['role' => 'user', 'content' => 'x']],
            LlmModelTier::HAIKU,
        );
    }

    public function testGeminiFlashTierIsRejectedInSprint54(): void
    {
        $this->client->expects($this->never())->method('chat');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/only routes claude_cli tiers.*gemini_flash/');

        $this->executor->executeWithRetry(
            'signal_aggregator',
            [['role' => 'user', 'content' => 'x']],
            LlmModelTier::GEMINI_FLASH,
        );
    }

    public function testSonnetTierIsAccepted(): void
    {
        $this->client->expects($this->once())
            ->method('chat')
            ->with(
                [['role' => 'user', 'content' => 'x']],
                'claude-sonnet-4-6',
                null,
            )
            ->willReturn('synthesis');

        $result = $this->executor->executeWithRetry(
            'context',
            [['role' => 'user', 'content' => 'x']],
            LlmModelTier::SONNET,
        );

        $this->assertSame('synthesis', $result['content']);
        $this->assertSame('sonnet', $result['tier']);
    }
}
