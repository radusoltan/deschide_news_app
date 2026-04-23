<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai;

use App\Enum\LlmModelTier;
use App\Service\Ai\AnthropicClientInterface;
use App\Service\Ai\Exception\ClaudeCliPermanentException;
use App\Service\Ai\Exception\ClaudeCliTransientException;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class LlmRetryExecutorTest extends TestCase
{
    private AnthropicClientInterface&MockObject $client;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private LlmRetryExecutor $executor;

    protected function setUp(): void
    {
        $this->client = $this->createMock(AnthropicClientInterface::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);
        $this->executor = new LlmRetryExecutor(
            $this->client,
            new NullLogger(),
            $this->invocationLogger,
            [0, 0, 0],
        );
    }

    public function testSuccessfulInvocationWritesBaselineRowAndThreadsUlidIntoReturn(): void
    {
        // T57.03 (ADR-023 D2) W' coverage — every successful Claude call
        // produces one llm_agent_call_log row with verdict=null and the
        // generated ULID comes back on the result array as `invocation_id`.
        $this->client->expects($this->once())
            ->method('chat')
            ->willReturn('payload');

        $this->invocationLogger->expects($this->once())
            ->method('logInvocation')
            ->with(
                $this->callback(fn (string $agent): bool => $agent === 'legal_guard'),
                $this->isString(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'claude-haiku-4-5-20251001',
                null,
            )
            ->willReturn('01JFXXXXXXXXXXXXXXXXXXXXXX');

        $result = $this->executor->executeWithRetry(
            'legal_guard',
            [['role' => 'user', 'content' => 'check']],
            LlmModelTier::HAIKU,
            'system',
        );

        $this->assertSame('01JFXXXXXXXXXXXXXXXXXXXXXX', $result['invocation_id']);
    }

    public function testLoggerReturningNullDoesNotBreakExecutor(): void
    {
        // Logger degrades gracefully (DB down) — the executor must still
        // return cleanly with invocation_id=null so downstream gates simply
        // skip attachVerdict.
        $this->client->method('chat')->willReturn('payload');
        $this->invocationLogger->method('logInvocation')->willReturn(null);

        $result = $this->executor->executeWithRetry(
            'style_guard',
            [['role' => 'user', 'content' => 'x']],
            LlmModelTier::HAIKU,
        );

        $this->assertNull($result['invocation_id']);
        $this->assertSame('payload', $result['content']);
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

    public function testExhaustedRetriesThrowLlmUnavailableWithoutFallbackTier(): void
    {
        // ADR-024 D3 (T57.P8) — retry exhaust hard-fails with a null
        // invocation_id (executor only opens an LlmAgentCallLog row on
        // successful invocations in P8; exhaust-row writing is S58+).
        $this->client->expects($this->exactly(4))
            ->method('chat')
            ->willThrowException(new ClaudeCliTransientException('529'));

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
            $this->assertSame(4, $e->attempts);
            $this->assertNull($e->getInvocationId(), 'invocation_id is null on exhaust — no row opened');
            $this->assertInstanceOf(ClaudeCliTransientException::class, $e->getPrevious());
        }
    }

    public function testPermanentExceptionThrowsImmediatelyWithNoRetry(): void
    {
        $this->client->expects($this->once())
            ->method('chat')
            ->willThrowException(new ClaudeCliPermanentException('invalid_api_key'));

        $this->expectException(ClaudeCliPermanentException::class);

        $this->executor->executeWithRetry(
            'source_attribution',
            [['role' => 'user', 'content' => 'x']],
            LlmModelTier::HAIKU,
        );
    }

    public function testGeminiFlashTierIsRejectedByClaudeCliTransportGuard(): void
    {
        // Executor remains claude_cli-only by construction post-T57.P7.C1;
        // Gemini transport is routed by AgentDispatcher directly.
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
