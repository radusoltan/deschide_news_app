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
final class GeminiCliService
{
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

        $this->logger->debug('GeminiCli: completed', [
            'outputLength' => mb_strlen($output),
        ]);

        return $output;
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
