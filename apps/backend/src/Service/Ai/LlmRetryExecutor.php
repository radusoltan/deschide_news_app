<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Enum\LlmModelTier;
use App\Service\Ai\Exception\ClaudeCliPermanentException;
use App\Service\Ai\Exception\ClaudeCliTransientException;
use App\Service\Ai\Exception\LlmUnavailableException;
use Psr\Log\LoggerInterface;
use Sentry\Breadcrumb;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

/**
 * Wraps an {@see AnthropicClientInterface} call with exponential-backoff retry
 * and fallback-detection logging (ADR-020 D5, Sprint 54 T54.2).
 *
 * Retry policy: up to 4 attempts total (1 initial + 3 retries) separated by
 * 2s / 4s / 8s sleeps. Retries fire only on transient subprocess errors —
 * rate-limit / 529 / overload patterns and process timeouts. 4xx-equivalent
 * permanent errors (auth, bad_request, malformed JSON) throw immediately.
 *
 * Fallback detection is log-only in Sprint 54: on exhaustion, if the agent
 * has a non-empty `agent.{id}.fallback` AppSetting, a structured log line
 * `llm_fallback_detected` and a Sentry breadcrumb are emitted, and the
 * caller receives an {@see LlmUnavailableException} with the fallback tier
 * attached. Actual provider rerouting is deferred to Sprint 56.
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
        private readonly TierResolver $tierResolver,
        private readonly LoggerInterface $logger,
        private readonly array $backoffSeconds = self::DEFAULT_BACKOFF_SECONDS,
    ) {}

    /**
     * @param list<array{role: string, content: string}> $messages
     *
     * @return array{content: string, agent_id: string, tier: string, model: string, attempts: int, fallback_detected: bool, metrics: array<string, mixed>|null}
     */
    public function executeWithRetry(
        string $agentId,
        array $messages,
        LlmModelTier $tier,
        ?string $systemPrompt = null,
    ): array {
        if ($tier->transport() !== 'claude_cli') {
            throw new \InvalidArgumentException(sprintf(
                'LlmRetryExecutor (S54) only routes claude_cli tiers; got "%s". '
                . 'Gemini provider rerouting lands in Sprint 56.',
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
                        'fallback_detected' => false,
                    ],
                    $this->extractMetricFields($metrics),
                ));

                return [
                    'content' => $content,
                    'agent_id' => $agentId,
                    'tier' => $tier->value,
                    'model' => $model,
                    'attempts' => $attempt,
                    'fallback_detected' => false,
                    'metrics' => $metrics,
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

        $fallback = $this->tierResolver->resolveFallback($agentId);
        $fallbackDetected = $fallback !== null;
        $fallbackTierValue = $fallback === null ? null : $fallback->value;
        $lastErrorMessage = $lastException->getMessage();

        $this->logger->error('llm_fallback_detected', [
            'agent_id' => $agentId,
            'tier' => $tier->value,
            'attempts' => self::MAX_ATTEMPTS,
            'fallback_detected' => $fallbackDetected,
            'fallback_tier' => $fallbackTierValue,
            'last_error' => $lastErrorMessage,
        ]);

        $this->breadcrumb(
            Breadcrumb::LEVEL_ERROR,
            'llm.fallback_detected',
            sprintf(
                'Exhausted %d attempts on agent=%s tier=%s; fallback=%s',
                self::MAX_ATTEMPTS,
                $agentId,
                $tier->value,
                $fallbackTierValue ?? 'none',
            ),
            [
                'agent_id' => $agentId,
                'tier' => $tier->value,
                'fallback_tier' => $fallbackTierValue,
                'attempts' => self::MAX_ATTEMPTS,
            ],
        );

        throw new LlmUnavailableException(
            agentId: $agentId,
            tier: $tier,
            fallbackTier: $fallback,
            attempts: self::MAX_ATTEMPTS,
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
