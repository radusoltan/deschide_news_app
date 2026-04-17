<?php

declare(strict_types=1);

namespace App\Service\Ai\Provider;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Centralized Gemini CLI transport layer.
 *
 * Replaces duplicated Process construction across 18+ services with a single
 * execute() method. Each consumer retains its own prompt-building and
 * response-mapping logic; only the subprocess plumbing is shared here.
 */
class GeminiCliService
{
    /**
     * USD cost per 1M tokens for each Gemini model (input, output).
     *
     * Pricing as published on ai.google.dev (April 2026). Flash is the
     * workhorse for Sprint 51c backfill; Pro listed for cross-workload use
     * (e.g. Sprint 51b audio, future fact-check refinement).
     *
     * @var array<string, array{in: float, out: float}>
     */
    private const MODEL_PRICING_PER_M = [
        'gemini-2.5-flash' => ['in' => 0.075, 'out' => 0.30],
        'gemini-2.5-pro'   => ['in' => 1.25,  'out' => 5.00],
    ];

    /** Fallback heuristic when the CLI envelope lacks usage metadata. */
    private const CHARS_PER_TOKEN = 4;

    private const DEFAULT_MODEL = 'gemini-2.5-flash';

    private int $sessionCalls = 0;
    private int $sessionInputTokens = 0;
    private int $sessionOutputTokens = 0;
    private float $sessionCostUsd = 0.0;

    public function __construct(
        private readonly string $geminiCliPath,
        private readonly string $homeDir,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Execute a Gemini CLI prompt and return raw output.
     *
     * @param string $prompt The prompt text (passed via -p flag)
     * @param array{
     *     timeout?: int,
     *     stdin?: string,
     *     jsonOutput?: bool,
     *     model?: string,
     *     cwd?: string,
     * } $options
     *
     * @return string Trimmed raw output from Gemini CLI
     *
     * @throws GeminiCliException on process failure or timeout
     */
    public function execute(string $prompt, array $options = []): string
    {
        $timeout = $options['timeout'] ?? 120;

        $args = [$this->geminiCliPath, '-p', $prompt];

        if ($options['jsonOutput'] ?? false) {
            $args[] = '-o';
            $args[] = 'json';
        }

        if (isset($options['model'])) {
            $args[] = '--model';
            $args[] = $options['model'];
        }

        $env = [
            'HOME' => $this->homeDir,
            'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
        ];

        $process = new Process(
            command: $args,
            cwd: $options['cwd'] ?? null,
            env: $env,
            timeout: $timeout,
        );

        if (isset($options['stdin'])) {
            $process->setInput($options['stdin']);
        }

        $this->logger->debug('GeminiCli: executing', [
            'timeout' => $timeout,
            'promptLength' => mb_strlen($prompt),
            'hasStdin' => isset($options['stdin']),
        ]);

        $startedAt = microtime(true);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            $this->logger->error('GeminiCli: process timed out', [
                'timeout' => $timeout,
            ]);

            throw new GeminiCliException(
                'Gemini CLI timed out after ' . $timeout . 's',
                0,
                $e,
                isTimeout: true,
            );
        }

        if (!$process->isSuccessful()) {
            $this->logger->error('GeminiCli: process failed', [
                'exitCode' => $process->getExitCode(),
                'stderr' => mb_substr($process->getErrorOutput(), 0, 500),
            ]);

            throw new GeminiCliException(
                'Gemini CLI failed (exit ' . ($process->getExitCode() ?? '?') . '): '
                . mb_substr($process->getErrorOutput(), 0, 200),
                $process->getExitCode() ?? 1,
            );
        }

        $output = trim($process->getOutput());

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $model = $options['model'] ?? self::DEFAULT_MODEL;
        $stdinLen = isset($options['stdin']) ? mb_strlen((string) $options['stdin']) : 0;
        [$inputTokens, $outputTokens] = $this->extractTokenCounts(
            $output,
            mb_strlen($prompt) + $stdinLen,
        );
        $costUsd = $this->calculateCost($model, $inputTokens, $outputTokens);

        ++$this->sessionCalls;
        $this->sessionInputTokens += $inputTokens;
        $this->sessionOutputTokens += $outputTokens;
        $this->sessionCostUsd += $costUsd;

        $this->logger->info('gemini_call', [
            'model' => $model,
            'duration_ms' => $durationMs,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost_usd' => round($costUsd, 6),
        ]);

        $this->logger->debug('GeminiCli: completed', [
            'outputLength' => mb_strlen($output),
        ]);

        return $output;
    }

    /**
     * Aggregated stats for this service instance (one per request/CLI run
     * under Symfony's default service scope).
     *
     * @return array{
     *     total_calls: int,
     *     total_input_tokens: int,
     *     total_output_tokens: int,
     *     total_cost_usd: float,
     * }
     */
    public function getSessionStats(): array
    {
        return [
            'total_calls' => $this->sessionCalls,
            'total_input_tokens' => $this->sessionInputTokens,
            'total_output_tokens' => $this->sessionOutputTokens,
            'total_cost_usd' => $this->sessionCostUsd,
        ];
    }

    /**
     * @return array{0: int, 1: int} [inputTokens, outputTokens]
     */
    private function extractTokenCounts(string $output, int $promptChars): array
    {
        // Prefer the Gemini CLI envelope metadata when present.
        $decoded = json_decode($output, true);
        if (\is_array($decoded)) {
            $usage = $decoded['response']['usageMetadata']
                ?? $decoded['usageMetadata']
                ?? $decoded['stats']['usageMetadata']
                ?? null;

            if (\is_array($usage)) {
                $input = (int) ($usage['promptTokenCount'] ?? 0);
                $output = (int) (
                    $usage['candidatesTokenCount']
                    ?? $usage['responseTokenCount']
                    ?? 0
                );

                if ($input > 0 || $output > 0) {
                    return [$input, $output];
                }
            }
        }

        // Fallback: chars / 4 — documented heuristic used by Gemini pricing docs.
        $outputChars = mb_strlen($output);

        return [
            (int) ceil($promptChars / self::CHARS_PER_TOKEN),
            (int) ceil($outputChars / self::CHARS_PER_TOKEN),
        ];
    }

    private function calculateCost(string $model, int $inputTokens, int $outputTokens): float
    {
        $pricing = self::MODEL_PRICING_PER_M[$model] ?? null;
        if ($pricing === null) {
            return 0.0;
        }

        return ($inputTokens / 1_000_000) * $pricing['in']
             + ($outputTokens / 1_000_000) * $pricing['out'];
    }

    /**
     * Strip markdown code fences from Gemini output.
     */
    public function stripFences(string $output): string
    {
        $cleaned = preg_replace('/^```(?:json|[\w]*)?\s*/m', '', $output) ?? $output;
        $cleaned = preg_replace('/\s*```\s*$/m', '', $cleaned) ?? $cleaned;

        return trim($cleaned);
    }

    /**
     * Parse JSON from Gemini output, handling markdown fences and the
     * Gemini CLI envelope format ({session_id, response, stats}).
     *
     * @return array<string, mixed>|list<mixed>
     *
     * @throws GeminiCliException on parse failure
     */
    public function parseJson(string $output): array
    {
        // Strip control characters
        $cleaned = $this->stripControlChars($output);
        $cleaned = $this->stripFences($cleaned);

        $decoded = json_decode($cleaned, true);
        if (json_last_error() === \JSON_ERROR_NONE && \is_array($decoded)) {
            // Handle Gemini CLI envelope: {session_id, response, stats}
            if (isset($decoded['response'])) {
                return $this->parseEnvelopeResponse($decoded['response']);
            }

            return $decoded;
        }

        // Try extracting JSON object or array from raw text
        return $this->extractJson($cleaned);
    }

    /**
     * Extract a JSON object ({...}) from text.
     *
     * @return array<string, mixed>
     *
     * @throws GeminiCliException if no JSON object found
     */
    public function extractJsonObject(string $text): array
    {
        $text = $this->stripControlChars($text);
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $json = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($json, true);
            if (json_last_error() === \JSON_ERROR_NONE && \is_array($decoded)) {
                return $decoded;
            }
        }

        throw new GeminiCliException('Failed to extract JSON object from Gemini response');
    }

    /**
     * Extract a JSON array ([...]) from text.
     *
     * @return list<mixed>
     *
     * @throws GeminiCliException if no JSON array found
     */
    public function extractJsonArray(string $text): array
    {
        $text = $this->stripControlChars($text);
        $start = strpos($text, '[');
        $end = strrpos($text, ']');

        if ($start !== false && $end !== false && $end > $start) {
            $json = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($json, true);
            if (json_last_error() === \JSON_ERROR_NONE && \is_array($decoded)) {
                return $decoded;
            }
        }

        throw new GeminiCliException('Failed to extract JSON array from Gemini response');
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function parseEnvelopeResponse(mixed $response): array
    {
        if (\is_array($response)) {
            return $response;
        }

        if (\is_string($response)) {
            $inner = $this->stripControlChars($response);
            $inner = $this->stripFences($inner);

            $decoded = json_decode($inner, true);
            if (json_last_error() === \JSON_ERROR_NONE && \is_array($decoded)) {
                return $decoded;
            }

            return $this->extractJson($inner);
        }

        throw new GeminiCliException('Unexpected Gemini envelope response type');
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    private function extractJson(string $text): array
    {
        // Try JSON object first
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $json = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($json, true);
            if (json_last_error() === \JSON_ERROR_NONE && \is_array($decoded)) {
                return $decoded;
            }
        }

        // Try JSON array
        $start = strpos($text, '[');
        $end = strrpos($text, ']');
        if ($start !== false && $end !== false && $end > $start) {
            $json = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($json, true);
            if (json_last_error() === \JSON_ERROR_NONE && \is_array($decoded)) {
                return $decoded;
            }
        }

        throw new GeminiCliException(
            'Failed to parse Gemini response as JSON: ' . json_last_error_msg()
        );
    }

    private function stripControlChars(string $text): string
    {
        $map = [];
        for ($i = 0; $i <= 0x1F; ++$i) {
            if ($i !== 0x0A && $i !== 0x0D && $i !== 0x09) { // Keep \n, \r, \t
                $map[\chr($i)] = ' ';
            }
        }
        $map[\chr(0x7F)] = ' ';

        return strtr($text, $map);
    }
}
