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
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Unit tests for {@see AgentDispatcher} (T57.P2a scaffolding, ADR-024 D2).
 *
 * Contract verified:
 *  - Happy path: request flows through LlmRetryExecutor, executor return
 *    array → typed AgentResponse DTO.
 *  - Tier passthrough: dispatcher does NOT resolve tier; it forwards the
 *    caller-resolved tier verbatim (ADR-024 Q2 — mechanical pipe).
 *  - editorial.emergency_halt raises EmergencyHaltException (non-handler
 *    callers; handlers keep their own fast-path unchanged).
 *  - LlmAgentCallLog is NOT written from the dispatcher — logging stays
 *    delegated to LlmRetryExecutor T57.03 W' baseline (ADR-024 Q4). This
 *    is asserted explicitly so a future contributor adding
 *    dispatcher-level logging fails the suite.
 *  - invocation_id propagates end-to-end through the array→DTO boundary
 *    (regression guard against typos in the mapping).
 */
class AgentDispatcherTest extends TestCase
{
    private LlmRetryExecutor&MockObject $executor;
    private AppSettingRepository&MockObject $appSettings;
    private AgentDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->executor = $this->createMock(LlmRetryExecutor::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);

        $this->dispatcher = new AgentDispatcher(
            $this->executor,
            $this->appSettings,
            new NullLogger(),
        );
    }

    #[Test]
    public function happyPathDispatchesThroughExecutorAndWrapsAsDto(): void
    {
        $this->appSettings->method('getBool')
            ->with('editorial.emergency_halt', false)
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
                'fallback_detected' => false,
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
            'fallback_detected' => false,
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
            'fallback_detected' => false,
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
            ->with('editorial.emergency_halt', false)
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
                'fallback_detected' => false,
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
            ->with('editorial.emergency_halt', false)
            ->willReturn(true);

        // Executor must NEVER be invoked when emergency_halt is active.
        $this->executor->expects($this->never())->method('executeWithRetry');

        $this->expectException(EmergencyHaltException::class);
        $this->expectExceptionMessageMatches('/editorial\.emergency_halt is active.*verification_gate/');

        $this->dispatcher->dispatch(new AgentRequest(
            agentId: 'verification_gate',
            messages: [['role' => 'user', 'content' => 'input']],
            tier: LlmModelTier::SONNET,
        ));
    }

    #[Test]
    public function dispatcherDoesNotDependOnLlmInvocationLoggerDirectly(): void
    {
        // Codifies ADR-024 Q4: dispatcher delegates LlmAgentCallLog open/close
        // to LlmRetryExecutor (T57.03 W' baseline). A dispatcher-level
        // LlmInvocationLogger dependency would double-count rows and violate
        // the 100% coverage-by-construction guarantee (because the executor
        // ALSO logs the same call).
        //
        // Architectural assertion: the constructor signature must NOT accept
        // LlmInvocationLogger. A future contributor adding that injection
        // would fail this test immediately — a stronger guarantee than
        // runtime "logger was never called" because it catches the intent
        // at compile/construction time before any call happens.
        $paramTypes = $this->extractConstructorParamTypes(AgentDispatcher::class);

        $this->assertNotContains(
            LlmInvocationLogger::class,
            $paramTypes,
            'AgentDispatcher must NOT depend on LlmInvocationLogger directly — '
            . 'logging is delegated to LlmRetryExecutor (ADR-024 Q4). If this '
            . 'constructor signature accepts LlmInvocationLogger, the ADR '
            . 'decision is being violated at the architectural level.',
        );
    }

    /**
     * @param class-string $class
     *
     * @return list<string>
     */
    private function extractConstructorParamTypes(string $class): array
    {
        $reflection = new \ReflectionClass($class);
        $ctor = $reflection->getConstructor();
        $this->assertNotNull($ctor, 'AgentDispatcher must have a constructor.');

        $types = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType) {
                $types[] = $type->getName();
            }
        }

        return $types;
    }
}
