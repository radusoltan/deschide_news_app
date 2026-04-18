<?php

declare(strict_types=1);

namespace App\Service\Ai;

use Psr\Log\LoggerInterface;
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
                $this->logger->warning('Claude CLI failed', [
                    'exitCode' => $process->getExitCode(),
                    'error' => mb_substr($process->getErrorOutput(), 0, 500),
                    'model' => $model,
                    'duration_ms' => $wallDuration,
                ]);

                throw new \RuntimeException(sprintf(
                    'Claude CLI exited with code %d: %s',
                    $process->getExitCode(),
                    mb_substr($process->getErrorOutput(), 0, 200),
                ));
            }

            $rawOutput = trim($process->getOutput());
            $json = json_decode($rawOutput, true);

            if (!\is_array($json) || !isset($json['result'])) {
                // Fallback: output wasn't JSON (shouldn't happen with --output-format json)
                $this->lastMetrics = null;
                $this->logger->warning('Claude CLI: unexpected non-JSON output', [
                    'output_preview' => mb_substr($rawOutput, 0, 200),
                ]);

                return $rawOutput;
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
        } catch (\Throwable $e) {
            if ($e instanceof \RuntimeException) {
                throw $e;
            }

            $this->logger->error('Claude CLI exception', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);

            throw new \RuntimeException('Claude CLI failed: ' . $e->getMessage(), previous: $e);
        }
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
