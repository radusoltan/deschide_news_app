<?php

declare(strict_types=1);

namespace App\Tests\Unit\Agent;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Dto\Agent\AgentResponse;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Ai\Logging\LlmInvocationLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Unit tests for {@see AgentDispatcher} (T57.P2a scaffolding + T57.P7.C1 Gemini
 * transport branch, ADR-024 D2).
 *
 * Contract verified:
 *  - Happy path: claude_cli request flows through LlmRetryExecutor, executor
 *    return array → typed AgentResponse DTO.
 *  - Tier passthrough: dispatcher does NOT resolve tier; it forwards the
 *    caller-resolved tier verbatim (ADR-024 Q2 — mechanical pipe).
 *  - agent.emergency_halt raises EmergencyHaltException on either transport
 *    BEFORE any LLM call (non-handler callers; handlers keep their own fast-path
 *    unchanged).
 *  - LlmAgentCallLog is NOT written from the dispatcher on the claude_cli
 *    branch — logging stays delegated to LlmRetryExecutor T57.03 W' baseline
 *    (ADR-024 Q4 preserved for that branch). Asserted at runtime so a future
 *    contributor adding dispatcher-level logging to the claude branch fails the
 *    suite.
 *  - LlmAgentCallLog IS written from the dispatcher on the gemini_cli branch —
 *    no executor exists for that transport (executor is claude_cli only per the
 *    S54 charter), so the dispatcher opens the row itself to honor ADR-024 D2
 *    100% coverage-by-construction.
 *  - invocation_id propagates end-to-end through the array→DTO boundary
 *    (regression guard against typos in the mapping).
 *  - GeminiCliException rethrown verbatim on gemini_cli failure (translator
 *    contract is "skip + manual flag" per ADR-024 D1; dispatcher does not
 *    retry or swallow).
 */
class AgentDispatcherTest extends TestCase
{
    private const PROJECT_DIR = '/tmp/dispatcher-test-project';

    private LlmRetryExecutor&MockObject $executor;
    private AppSettingRepository&MockObject $appSettings;
    private GeminiCliService&MockObject $geminiCli;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private AgentDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->executor = $this->createMock(LlmRetryExecutor::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);
        $this->geminiCli = $this->createMock(GeminiCliService::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);

        $this->dispatcher = new AgentDispatcher(
            $this->executor,
            $this->appSettings,
            new NullLogger(),
            $this->geminiCli,
            $this->invocationLogger,
            self::PROJECT_DIR,
        );
    }

    #[Test]
    public function happyPathDispatchesThroughExecutorAndWrapsAsDto(): void
    {
        $this->appSettings->method('getBool')
            ->with('agent.emergency_halt', false)
            ->willReturn(false);

        $this->executor->expects($this->once())
            ->method('executeWithRetry')
            ->with(
                agentId: 'source_attribution',
                messages: [['role' => 'user', 'content' => 'analyze']],
                tier: LlmModelTier::HAIKU,
                systemPrompt: 'You are an analyst.',
            )
            ->willReturn([
                'content' => '{"source_attribution":"Reuters","source_links_out":[]}',
                'agent_id' => 'source_attribution',
                'tier' => 'haiku',
                'model' => 'claude-haiku-4-5-20251001',
                'attempts' => 1,
                'metrics' => ['input_tokens' => 100, 'output_tokens' => 30],
                'invocation_id' => '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
            ]);

        $request = new AgentRequest(
            agentId: 'source_attribution',
            messages: [['role' => 'user', 'content' => 'analyze']],
            tier: LlmModelTier::HAIKU,
            systemPrompt: 'You are an analyst.',
        );

        $response = $this->dispatcher->dispatch($request);

        $this->assertInstanceOf(AgentResponse::class, $response);
        $this->assertSame('{"source_attribution":"Reuters","source_links_out":[]}', $response->content);
        $this->assertSame('source_attribution', $response->agentId);
        $this->assertSame(LlmModelTier::HAIKU, $response->tier);
        $this->assertSame('claude-haiku-4-5-20251001', $response->model);
        $this->assertSame(1, $response->attempts);
        $this->assertSame(['input_tokens' => 100, 'output_tokens' => 30], $response->metrics);
    }

    #[Test]
    public function invocationIdPropagatesEndToEndThroughDtoBoundary(): void
    {
        $knownUlid = '01JE0Q9ZXJQ8YHZR3S3M7E2P5H';

        $this->appSettings->method('getBool')->willReturn(false);
        $this->executor->method('executeWithRetry')->willReturn([
            'content' => 'body',
            'agent_id' => 'signal_aggregator',
            'tier' => 'sonnet',
            'model' => 'claude-sonnet-4-6',
            'attempts' => 1,
            'metrics' => null,
            'invocation_id' => $knownUlid,
        ]);

        $response = $this->dispatcher->dispatch(new AgentRequest(
            agentId: 'signal_aggregator',
            messages: [['role' => 'user', 'content' => 'input']],
            tier: LlmModelTier::SONNET,
        ));

        $this->assertSame(
            $knownUlid,
            $response->invocationId,
            'invocation_id must round-trip verbatim through array→DTO conversion; '
            . 'any typo in AgentDispatcher::dispatch mapping would break the audit-trail linkage.',
        );
    }

    #[Test]
    public function invocationIdIsNullWhenExecutorReturnsNoId(): void
    {
        $this->appSettings->method('getBool')->willReturn(false);
        $this->executor->method('executeWithRetry')->willReturn([
            'content' => 'body',
            'agent_id' => 'context',
            'tier' => 'sonnet',
            'model' => 'claude-sonnet-4-6',
            'attempts' => 1,
            'metrics' => null,
            // invocation_id absent — LlmInvocationLogger persistence failure case
        ]);

        $response = $this->dispatcher->dispatch(new AgentRequest(
            agentId: 'context',
            messages: [['role' => 'user', 'content' => 'input']],
            tier: LlmModelTier::SONNET,
        ));

        $this->assertNull($response->invocationId);
    }

    #[Test]
    public function tierPassesThroughVerbatimDispatcherDoesNotResolve(): void
    {
        $this->appSettings->method('getBool')
            ->with('agent.emergency_halt', false)
            ->willReturn(false);

        // Assert the tier received by executor matches the caller-passed tier
        // EXACTLY, proving the dispatcher never looks up agent.{id}.model_tier
        // itself (ADR-024 Q2).
        $this->executor->expects($this->once())
            ->method('executeWithRetry')
            ->with(
                agentId: 'flash_writer',
                messages: $this->isArray(),
                tier: LlmModelTier::SONNET,
                systemPrompt: $this->isString(),
            )
            ->willReturn([
                'content' => 'body',
                'agent_id' => 'flash_writer',
                'tier' => 'sonnet',
                'model' => 'claude-sonnet-4-6',
                'attempts' => 1,
                'metrics' => null,
                'invocation_id' => 'x',
            ]);

        // Caller resolves SONNET (e.g., post-P4 hypothetical override); if
        // dispatcher were doing internal resolution against current AppSettings
        // it would pass HAIKU (current flash_writer default), the expectation
        // above would fail.
        $this->dispatcher->dispatch(new AgentRequest(
            agentId: 'flash_writer',
            messages: [['role' => 'user', 'content' => 'draft']],
            tier: LlmModelTier::SONNET,
            systemPrompt: 'You are an editor.',
        ));
    }

    #[Test]
    public function emergencyHaltRaisesEmergencyHaltExceptionBeforeLlmCall(): void
    {
        $this->appSettings->expects($this->once())
            ->method('getBool')
            ->with('agent.emergency_halt', false)
            ->willReturn(true);

        // Executor must NEVER be invoked when emergency_halt is active.
        $this->executor->expects($this->never())->method('executeWithRetry');

        $this->expectException(EmergencyHaltException::class);
        $this->expectExceptionMessageMatches('/agent\.emergency_halt is active.*verification_gate/');

        $this->dispatcher->dispatch(new AgentRequest(
            agentId: 'verification_gate',
            messages: [['role' => 'user', 'content' => 'input']],
            tier: LlmModelTier::SONNET,
        ));
    }

    #[Test]
    public function claudeBranchDoesNotInvokeLlmInvocationLoggerDirectly(): void
    {
        // Codifies ADR-024 Q4 (post-T57.P7 dual-transport reality): dispatcher
        // delegates LlmAgentCallLog open/close to LlmRetryExecutor on the
        // claude_cli branch (T57.03 W' baseline). A dispatcher-level
        // LlmInvocationLogger call on the claude branch would double-count rows
        // and violate the 100% coverage-by-construction guarantee.
        //
        // The gemini_cli branch DOES invoke invocationLogger directly because
        // the executor rejects non-claude tiers (S54 charter); that separate
        // contract is covered by geminiTransportOpensLlmAgentCallLogRow().
        $this->appSettings->method('getBool')->willReturn(false);
        $this->executor->method('executeWithRetry')->willReturn([
            'content' => 'body',
            'agent_id' => 'flash_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'metrics' => null,
            'invocation_id' => '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
        ]);

        $this->invocationLogger->expects($this->never())->method('logInvocation');

        $this->dispatcher->dispatch(new AgentRequest(
            agentId: 'flash_writer',
            messages: [['role' => 'user', 'content' => 'draft']],
            tier: LlmModelTier::HAIKU,
        ));
    }

    // -------------------------------------------------------------------------
    // T57.P7.C1 — Gemini transport branch
    // -------------------------------------------------------------------------

    #[Test]
    public function geminiTransportRoutesToGeminiCliService(): void
    {
        $this->appSettings->method('getBool')
            ->with('agent.emergency_halt', false)
            ->willReturn(false);

        // Timeout lookup: agent.journalistic_translator.timeout_seconds, default 300.
        $this->appSettings->expects($this->once())
            ->method('getInt')
            ->with('agent.journalistic_translator.timeout_seconds', 300)
            ->willReturn(300);

        // Claude path must NOT be touched on a gemini_cli request.
        $this->executor->expects($this->never())->method('executeWithRetry');

        // A1 sub-mapping: systemPrompt → stdin preamble; messages[0].content → stdin body.
        $expectedStdin = "agent file contents\n\n---\n\nuser article JSON";

        $this->geminiCli->expects($this->once())
            ->method('execute')
            ->with(
                'Translate following the instructions. Return JSON.',
                $this->callback(function (array $options) use ($expectedStdin): bool {
                    return ($options['stdin'] ?? null) === $expectedStdin
                        && ($options['jsonOutput'] ?? false) === true
                        && ($options['timeout'] ?? null) === 300
                        && ($options['cwd'] ?? null) === self::PROJECT_DIR;
                }),
            )
            ->willReturn('{"translations":{"en":{"title":"ok"}}}');

        $this->invocationLogger->expects($this->once())
            ->method('logInvocation')
            ->with(
                agentName: 'journalistic_translator',
                promptHash: $this->isString(),
                durationMs: $this->isInt(),
                inputTokens: 0,
                outputTokens: 0,
                cacheReadTokens: 0,
                cacheCreationTokens: 0,
                costUsd: 0.0,
                model: 'gemini-2.5-flash',
                verdict: null,
            )
            ->willReturn('01JKNOWNULIDFORGEMINI000001');

        $response = $this->dispatcher->dispatch(new AgentRequest(
            agentId: 'journalistic_translator',
            messages: [['role' => 'user', 'content' => 'user article JSON']],
            tier: LlmModelTier::GEMINI_FLASH,
            systemPrompt: 'agent file contents',
        ));

        $this->assertInstanceOf(AgentResponse::class, $response);
        $this->assertSame('{"translations":{"en":{"title":"ok"}}}', $response->content);
        $this->assertSame('journalistic_translator', $response->agentId);
        $this->assertSame(LlmModelTier::GEMINI_FLASH, $response->tier);
        $this->assertSame('gemini-2.5-flash', $response->model);
        $this->assertSame(1, $response->attempts);
        $this->assertSame('01JKNOWNULIDFORGEMINI000001', $response->invocationId);
        $this->assertNull($response->metrics);
    }

    #[Test]
    public function geminiTransportRaisesEmergencyHaltBeforeAnyCall(): void
    {
        $this->appSettings->expects($this->once())
            ->method('getBool')
            ->with('agent.emergency_halt', false)
            ->willReturn(true);

        // Keeper pattern #3 — mock never() on the downstream transport to prove
        // halt blocks BEFORE the Gemini subprocess spawns.
        $this->geminiCli->expects($this->never())->method('execute');
        $this->invocationLogger->expects($this->never())->method('logInvocation');
        $this->executor->expects($this->never())->method('executeWithRetry');

        $this->expectException(EmergencyHaltException::class);
        $this->expectExceptionMessageMatches('/agent\.emergency_halt is active.*journalistic_translator/');

        $this->dispatcher->dispatch(new AgentRequest(
            agentId: 'journalistic_translator',
            messages: [['role' => 'user', 'content' => 'user article JSON']],
            tier: LlmModelTier::GEMINI_FLASH,
            systemPrompt: 'agent file contents',
        ));
    }

    #[Test]
    public function geminiCliExceptionRethrownVerbatim(): void
    {
        $this->appSettings->method('getBool')->willReturn(false);
        $this->appSettings->method('getInt')->willReturn(300);

        $originalException = new GeminiCliException('Gemini CLI timed out after 300s', 0, null, isTimeout: true);

        $this->geminiCli->expects($this->once())
            ->method('execute')
            ->willThrowException($originalException);

        // No log row opened on failure — matches LlmRetryExecutor contract
        // (only successful calls produce an LlmAgentCallLog row).
        $this->invocationLogger->expects($this->never())->method('logInvocation');

        try {
            $this->dispatcher->dispatch(new AgentRequest(
                agentId: 'journalistic_translator',
                messages: [['role' => 'user', 'content' => 'user article JSON']],
                tier: LlmModelTier::GEMINI_FLASH,
                systemPrompt: 'agent file contents',
            ));
            $this->fail('Expected GeminiCliException to propagate from dispatcher.');
        } catch (GeminiCliException $caught) {
            $this->assertSame($originalException, $caught, 'Dispatcher must rethrow the exact same exception instance (no retry, no wrapping).');
            $this->assertTrue($caught->isTimeout());
        }
    }

    #[Test]
    public function geminiTransportOpensLlmAgentCallLogRowWithPromptHash(): void
    {
        $this->appSettings->method('getBool')->willReturn(false);
        $this->appSettings->method('getInt')->willReturn(300);

        $this->geminiCli->method('execute')->willReturn('{"translations":{}}');

        // Capture positional args exposed by the mock (PHPUnit normalizes
        // named-arg calls to positional in declaration order).
        $capturedArgs = null;
        $this->invocationLogger->expects($this->once())
            ->method('logInvocation')
            ->willReturnCallback(function (
                string $agentName,
                string $promptHash,
                int $durationMs,
                int $inputTokens,
                int $outputTokens,
                int $cacheReadTokens,
                int $cacheCreationTokens,
                float $costUsd,
                ?string $model,
                ?string $verdict,
            ) use (&$capturedArgs): string {
                $capturedArgs = compact(
                    'agentName',
                    'promptHash',
                    'durationMs',
                    'inputTokens',
                    'outputTokens',
                    'costUsd',
                    'model',
                    'verdict',
                );

                return '01JCAPTURED000000000000001';
            });

        $response = $this->dispatcher->dispatch(new AgentRequest(
            agentId: 'journalistic_translator',
            messages: [['role' => 'user', 'content' => 'user content']],
            tier: LlmModelTier::GEMINI_FLASH,
            systemPrompt: 'system prompt',
        ));

        $this->assertIsArray($capturedArgs);
        $this->assertSame('journalistic_translator', $capturedArgs['agentName']);
        $this->assertSame('gemini-2.5-flash', $capturedArgs['model']);
        $this->assertNull($capturedArgs['verdict']);
        $this->assertSame(0, $capturedArgs['inputTokens']);
        $this->assertSame(0, $capturedArgs['outputTokens']);
        $this->assertSame(0.0, $capturedArgs['costUsd']);

        // Prompt hash must reflect the exact stdin assembled by the dispatcher
        // (systemPrompt + "\n\n---\n\n" + user content). Hash changes if A1
        // sub-mapping changes — this test pins the contract.
        $this->assertSame(
            hash('xxh128', "system prompt\n\n---\n\nuser content"),
            $capturedArgs['promptHash'],
        );

        $this->assertGreaterThanOrEqual(0, $capturedArgs['durationMs']);

        $this->assertSame('01JCAPTURED000000000000001', $response->invocationId);
    }
}
