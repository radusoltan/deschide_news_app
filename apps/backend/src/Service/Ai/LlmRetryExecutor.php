<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Enum\LlmModelTier;
use App\Service\Ai\Exception\ClaudeCliPermanentException;
use App\Service\Ai\Exception\ClaudeCliTransientException;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use Psr\Log\LoggerInterface;
use Sentry\Breadcrumb;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Wraps an {@see AnthropicClientInterface} call with exponential-backoff retry
 * on the caller-supplied tier. Per ADR-024 D3 (T57.P8) the downgrade-only
 * policy has been retired — on exhaust the executor throws
 * {@see LlmUnavailableException} and callers log `editorial_review_queue`
 * before rethrowing; there is no automatic cross-provider fallback.
 *
 * Retry policy: up to 4 attempts total (1 initial + 3 retries) separated by
 * 2s / 4s / 8s sleeps. Retries fire only on transient subprocess errors —
 * rate-limit / 529 / overload patterns and process timeouts. 4xx-equivalent
 * permanent errors (auth, bad_request, malformed JSON) throw immediately.
 *
 * Transport guard: the executor only routes tiers whose transport is
 * `claude_cli`; non-Claude tiers (e.g. `gemini_flash`) are rejected with
 * `\InvalidArgumentException`. Gemini transport is handled separately by
 * {@see \App\Agent\AgentDispatcher} since T57.P7.C1.
 *
 * Cost observability: when the underlying client is a {@see ClaudeCliClient},
 * the CLI JSON envelope's token/cost metrics are copied into the `llm_agent_call`
 * log line and the return array.
 */
class LlmRetryExecutor
{
    /** Total attempts including the initial call. */
    private const MAX_ATTEMPTS = 4;

    /** Default backoff delays (seconds) before attempts 2, 3, 4. */
    private const DEFAULT_BACKOFF_SECONDS = [2, 4, 8];

    /**
     * @param list<int> $backoffSeconds delays before retries 1..N. Length must be MAX_ATTEMPTS-1.
     *                                  Tests override with [0,0,0] to skip sleeps.
     */
    public function __construct(
        private readonly AnthropicClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly LlmInvocationLogger $invocationLogger,
        private readonly array $backoffSeconds = self::DEFAULT_BACKOFF_SECONDS,
    ) {}

    /**
     * @param list<array{role: string, content: string}> $messages
     *
     * @return array{content: string, agent_id: string, tier: string, model: string, attempts: int, metrics: array<string, mixed>|null, invocation_id: string|null}
     */
    public function executeWithRetry(
        string $agentId,
        array $messages,
        LlmModelTier $tier,
        ?string $systemPrompt = null,
    ): array {
        if ($tier->transport() !== 'claude_cli') {
            throw new \InvalidArgumentException(sprintf(
                'LlmRetryExecutor only routes claude_cli tiers; got "%s". '
                . 'Gemini transport is handled by AgentDispatcher directly (T57.P7.C1).',
                $tier->value,
            ));
        }

        $model = $tier->toModelString();

        $this->breadcrumb(
            Breadcrumb::LEVEL_INFO,
            'llm.execute.start',
            sprintf('Start LLM call: agent=%s tier=%s', $agentId, $tier->value),
            ['agent_id' => $agentId, 'tier' => $tier->value, 'model' => $model],
        );

        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                $content = $this->client->chat($messages, $model, $systemPrompt);
                $metrics = $this->client instanceof ClaudeCliClient
                    ? $this->client->getLastMetrics()
                    : null;

                $this->logger->info('llm_agent_call', array_merge(
                    [
                        'agent_id' => $agentId,
                        'tier' => $tier->value,
                        'model' => $model,
                        'attempts' => $attempt,
                        'transport' => $tier->transport(),
                    ],
                    $this->extractMetricFields($metrics),
                ));

                // T57.03 (ADR-023 D2) — W' baseline row. Fires on every
                // successful Claude invocation; writers leave verdict=null,
                // gates UPDATE via LlmInvocationLogger::attachVerdict after
                // they parse the response.
                $promptHash = hash(
                    'xxh128',
                    implode('|', array_column($messages, 'content')) . ($systemPrompt ?? ''),
                );
                $invocationId = $this->invocationLogger->logInvocation(
                    agentName: $agentId,
                    promptHash: $promptHash,
                    durationMs: (int) ($metrics['duration_ms'] ?? 0),
                    inputTokens: (int) ($metrics['input_tokens'] ?? 0),
                    outputTokens: (int) ($metrics['output_tokens'] ?? 0),
                    cacheReadTokens: (int) ($metrics['cache_read_tokens'] ?? 0),
                    cacheCreationTokens: (int) ($metrics['cache_creation_tokens'] ?? 0),
                    costUsd: (float) ($metrics['cost_usd'] ?? 0.0),
                    model: $model,
                    verdict: null,
                );

                return [
                    'content' => $content,
                    'agent_id' => $agentId,
                    'tier' => $tier->value,
                    'model' => $model,
                    'attempts' => $attempt,
                    'metrics' => $metrics,
                    'invocation_id' => $invocationId,
                ];
            } catch (ClaudeCliTransientException | ProcessTimedOutException $e) {
                $lastException = $e;

                if ($attempt >= self::MAX_ATTEMPTS) {
                    break;
                }

                $delay = $this->backoffSeconds[$attempt - 1] ?? 0;

                $this->logger->warning('llm_agent_retry', [
                    'agent_id' => $agentId,
                    'tier' => $tier->value,
                    'attempt' => $attempt,
                    'next_delay_seconds' => $delay,
                    'error' => $e->getMessage(),
                ]);

                $this->breadcrumb(
                    Breadcrumb::LEVEL_WARNING,
                    'llm.retry',
                    sprintf('Retry %d after %ds (agent=%s)', $attempt, $delay, $agentId),
                    [
                        'agent_id' => $agentId,
                        'tier' => $tier->value,
                        'attempt' => $attempt,
                        'delay_s' => $delay,
                    ],
                );

                if ($delay > 0) {
                    sleep($delay);
                }
            } catch (ClaudeCliPermanentException $e) {
                $this->logger->error('llm_agent_permanent_failure', [
                    'agent_id' => $agentId,
                    'tier' => $tier->value,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }

        $this->logger->error('llm_retry_exhausted', [
            'agent_id' => $agentId,
            'tier' => $tier->value,
            'attempts' => self::MAX_ATTEMPTS,
            'last_error' => $lastException->getMessage(),
        ]);

        $this->breadcrumb(
            Breadcrumb::LEVEL_ERROR,
            'llm.retry_exhausted',
            sprintf(
                'Exhausted %d attempts on agent=%s tier=%s',
                self::MAX_ATTEMPTS,
                $agentId,
                $tier->value,
            ),
            [
                'agent_id' => $agentId,
                'tier' => $tier->value,
                'attempts' => self::MAX_ATTEMPTS,
            ],
        );

        throw new LlmUnavailableException(
            agentId: $agentId,
            tier: $tier,
            attempts: self::MAX_ATTEMPTS,
            invocationId: null,
            previous: $lastException,
        );
    }

    /**
     * @param array<string, mixed>|null $metrics
     *
     * @return array<string, mixed>
     */
    private function extractMetricFields(?array $metrics): array
    {
        return [
            'input_tokens' => $metrics['input_tokens'] ?? null,
            'output_tokens' => $metrics['output_tokens'] ?? null,
            'cache_read_tokens' => $metrics['cache_read_tokens'] ?? null,
            'cache_creation_tokens' => $metrics['cache_creation_tokens'] ?? null,
            'cost_usd' => $metrics['cost_usd'] ?? null,
            'duration_ms' => $metrics['duration_ms'] ?? null,
            'duration_api_ms' => $metrics['duration_api_ms'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function breadcrumb(string $level, string $category, string $message, array $data): void
    {
        if (!class_exists(Breadcrumb::class)) {
            return;
        }

        \Sentry\addBreadcrumb(new Breadcrumb(
            $level,
            Breadcrumb::TYPE_DEFAULT,
            $category,
            $message,
            $data,
        ));
    }
}
