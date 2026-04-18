<?php

declare(strict_types=1);

namespace App\Service\Ai;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AnthropicApiClient implements AnthropicClientInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MAX_RETRIES = 3;
    private const MAX_TOKENS = 4096;
    private const RETRYABLE_STATUS_CODES = [429, 529];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $anthropicApiKey,
        private readonly int $maxTokens = self::MAX_TOKENS,
    ) {}

    public function chat(array $messages, string $model, ?string $system = null): string
    {
        $startTime = microtime(true);
        $body = $this->buildRequestBody($messages, $model, $system, stream: false);

        $response = $this->requestWithRetry($body);
        $data = $response;

        $duration = round((microtime(true) - $startTime) * 1000);
        $inputTokens = $data['usage']['input_tokens'] ?? 0;
        $outputTokens = $data['usage']['output_tokens'] ?? 0;

        $this->logger->info('Anthropic API request completed', [
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'duration_ms' => $duration,
        ]);

        return $data['content'][0]['text'] ?? '';
    }

    public function chatStream(array $messages, string $model, ?string $system = null): \Generator
    {
        $body = $this->buildRequestBody($messages, $model, $system, stream: true);

        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => $this->buildHeaders(),
            'json' => $body,
        ]);

        $buffer = '';
        foreach ($this->httpClient->stream($response) as $chunk) {
            $buffer .= $chunk->getContent();

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $json = substr($line, 6);
                if ($json === '[DONE]') {
                    return;
                }

                $event = json_decode($json, true);
                if ($event === null) {
                    continue;
                }

                if (($event['type'] ?? '') === 'content_block_delta'
                    && ($event['delta']['type'] ?? '') === 'text_delta') {
                    yield $event['delta']['text'];
                }
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestBody(array $messages, string $model, ?string $system, bool $stream): array
    {
        $body = [
            'model' => $model,
            'max_tokens' => $this->maxTokens,
            'messages' => $messages,
            'stream' => $stream,
        ];

        if ($system !== null) {
            $body['system'] = $system;
        }

        return $body;
    }

    /**
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        return [
            'x-api-key' => $this->anthropicApiKey,
            'anthropic-version' => self::API_VERSION,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Execute request with exponential backoff retry on 429/529.
     *
     * @return array<string, mixed>
     */
    private function requestWithRetry(array $body): array
    {
        $lastException = null;

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; ++$attempt) {
            try {
                $response = $this->httpClient->request('POST', self::API_URL, [
                    'headers' => $this->buildHeaders(),
                    'json' => $body,
                ]);

                $statusCode = $response->getStatusCode();

                if (\in_array($statusCode, self::RETRYABLE_STATUS_CODES, true)) {
                    if ($attempt < self::MAX_RETRIES) {
                        $delay = (int) (pow(2, $attempt) * 1000000); // exponential backoff in microseconds
                        $this->logger->warning('Anthropic API rate limited, retrying', [
                            'status' => $statusCode,
                            'attempt' => $attempt + 1,
                            'delay_ms' => $delay / 1000,
                        ]);
                        usleep($delay);

                        continue;
                    }
                }

                return $response->toArray();
            } catch (\Throwable $e) {
                $lastException = $e;
                $this->logger->error('Anthropic API request failed', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    usleep((int) (pow(2, $attempt) * 1000000));
                }
            }
        }

        throw new \RuntimeException(
            'Anthropic API request failed after ' . self::MAX_RETRIES . ' retries',
            previous: $lastException,
        );
    }
}
