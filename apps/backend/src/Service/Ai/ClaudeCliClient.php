<?php

declare(strict_types=1);

namespace App\Service\Ai;

use App\Service\Ai\Exception\ClaudeCliPermanentException;
use App\Service\Ai\Exception\ClaudeCliTransientException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * AnthropicClient implementation that uses the Claude Code CLI (`claude -p`).
 * Same pattern as Gemini CLI used by translation/editorial services.
 *
 * Uses --output-format json to capture token usage and cost metrics.
 */
final class ClaudeCliClient implements AnthropicClientInterface
{
    private const DEFAULT_TIMEOUT = 120;

    /**
     * Last request metrics — accessible after each chat() call.
     *
     * @var array{input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_creation_tokens: int, cost_usd: float, duration_ms: int, model: string}|null
     */
    private ?array $lastMetrics = null;

    public function __construct(
        private readonly string $claudeCliPath,
        private readonly LoggerInterface $logger,
        private readonly int $timeout = self::DEFAULT_TIMEOUT,
    ) {}

    public function chat(array $messages, string $model, ?string $system = null): string
    {
        $prompt = $this->buildPrompt($messages, $system);
        $startTime = microtime(true);

        $process = new Process(
            [$this->claudeCliPath, '-p', $prompt, '--output-format', 'json', '--model', $model],
        );
        $process->setTimeout($this->timeout);

        try {
            $process->run();

            $wallDuration = (int) round((microtime(true) - $startTime) * 1000);

            if (!$process->isSuccessful()) {
                $exitCode = $process->getExitCode() ?? -1;
                $stderr = mb_substr($process->getErrorOutput(), 0, 500);

                $this->logger->warning('Claude CLI failed', [
                    'exitCode' => $exitCode,
                    'error' => $stderr,
                    'model' => $model,
                    'duration_ms' => $wallDuration,
                ]);

                $message = sprintf(
                    'Claude CLI exited with code %d: %s',
                    $exitCode,
                    mb_substr($stderr, 0, 200),
                );

                throw $this->isTransientStderr($stderr)
                    ? new ClaudeCliTransientException($message)
                    : new ClaudeCliPermanentException($message);
            }

            $rawOutput = trim($process->getOutput());
            $json = json_decode($rawOutput, true);

            if (!\is_array($json) || !isset($json['result'])) {
                $this->lastMetrics = null;
                $this->logger->warning('Claude CLI: unexpected non-JSON output', [
                    'output_preview' => mb_substr($rawOutput, 0, 200),
                ]);

                throw new ClaudeCliPermanentException(sprintf(
                    'Claude CLI returned malformed JSON output (model=%s, preview=%s)',
                    $model,
                    mb_substr($rawOutput, 0, 120),
                ));
            }

            // Extract metrics
            $usage = $json['usage'] ?? [];
            $this->lastMetrics = [
                'input_tokens' => $usage['input_tokens'] ?? 0,
                'output_tokens' => $usage['output_tokens'] ?? 0,
                'cache_read_tokens' => $usage['cache_read_input_tokens'] ?? 0,
                'cache_creation_tokens' => $usage['cache_creation_input_tokens'] ?? 0,
                'cost_usd' => $json['total_cost_usd'] ?? 0.0,
                'duration_ms' => $json['duration_ms'] ?? $wallDuration,
                'duration_api_ms' => $json['duration_api_ms'] ?? 0,
                'model' => $model,
            ];

            $this->logger->info('Claude CLI request completed', [
                'model' => $model,
                'input_tokens' => $this->lastMetrics['input_tokens'],
                'output_tokens' => $this->lastMetrics['output_tokens'],
                'cache_read_tokens' => $this->lastMetrics['cache_read_tokens'],
                'cache_creation_tokens' => $this->lastMetrics['cache_creation_tokens'],
                'cost_usd' => round($this->lastMetrics['cost_usd'], 6),
                'duration_ms' => $this->lastMetrics['duration_ms'],
                'duration_api_ms' => $this->lastMetrics['duration_api_ms'],
            ]);

            return $json['result'];
        } catch (ProcessTimedOutException $e) {
            $this->logger->warning('Claude CLI timed out', [
                'error' => $e->getMessage(),
                'model' => $model,
                'timeout' => $this->timeout,
            ]);

            throw new ClaudeCliTransientException(
                sprintf('Claude CLI timed out after %ds (model=%s)', $this->timeout, $model),
                previous: $e,
            );
        } catch (ClaudeCliTransientException | ClaudeCliPermanentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger->error('Claude CLI exception', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);

            throw new ClaudeCliPermanentException(
                'Claude CLI failed: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }

    /**
     * Classify a stderr line as transient (retryable). Checks for rate-limit
     * signals and overload codes that indicate the next attempt is likely to
     * succeed after backoff. Anything else is treated as permanent.
     */
    private function isTransientStderr(string $stderr): bool
    {
        $lower = mb_strtolower($stderr);
        foreach (['rate_limit', 'rate limit', '529', 'overload', 'overloaded'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function chatStream(array $messages, string $model, ?string $system = null): \Generator
    {
        $response = $this->chat($messages, $model, $system);

        yield $response;
    }

    /**
     * Get metrics from the last chat() call.
     *
     * @return array{input_tokens: int, output_tokens: int, cache_read_tokens: int, cache_creation_tokens: int, cost_usd: float, duration_ms: int, model: string}|null
     */
    public function getLastMetrics(): ?array
    {
        return $this->lastMetrics;
    }

    private function buildPrompt(array $messages, ?string $system): string
    {
        $parts = [];

        if ($system !== null && $system !== '') {
            $parts[] = "[System]\n" . $system;
        }

        foreach ($messages as $msg) {
            $role = $msg['role'] === 'user' ? 'User' : 'Assistant';
            $parts[] = "[{$role}]\n" . $msg['content'];
        }

        return implode("\n\n", $parts);
    }
}
