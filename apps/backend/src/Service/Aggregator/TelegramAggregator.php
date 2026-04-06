<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AutoconfigureTag('app.aggregator')]
final readonly class TelegramAggregator implements AggregatorInterface
{
    private const URL_PATTERN = '#https?://[^\s<>"\']+#';
    private const OFFSET_CACHE_PREFIX = 'telegram_offset_';
    private const OFFSET_TTL = 604800; // 7 days
    private const MESSAGES_LIMIT = 50;

    /**
     * @param list<array{name: string, username: string, type: string}> $channels
     */
    public function __construct(
        private TelegramSessionManager $sessionManager,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        #[Autowire('%telegram.channels%')]
        private array $channels,
        #[Autowire('%telegram.rate_limit_seconds%')]
        private int $rateLimitSeconds,
        #[Autowire('%telegram.enabled%')]
        private bool $enabled,
    ) {}

    public function fetch(): array
    {
        if (!$this->enabled) {
            $this->logger->info('TelegramAggregator: disabled, skipping.');

            return [];
        }

        if (!$this->sessionManager->isConfigured()) {
            $this->logger->info('TelegramAggregator: API credentials not configured, skipping.');

            return [];
        }

        $results = [];

        foreach ($this->channels as $channel) {
            try {
                $channelResults = $this->fetchChannel($channel);
                $results = array_merge($results, $channelResults);

                $this->logger->info('TelegramAggregator: fetched from channel', [
                    'channel' => $channel['username'],
                    'count' => \count($channelResults),
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning('TelegramAggregator: channel fetch failed', [
                    'channel' => $channel['username'],
                    'error' => $e->getMessage(),
                ]);
            }

            // Rate limit between channels
            if ($this->rateLimitSeconds > 0) {
                sleep($this->rateLimitSeconds);
            }
        }

        $this->logger->info('TelegramAggregator: total results fetched', [
            'total' => \count($results),
            'channels' => \count($this->channels),
        ]);

        return $results;
    }

    public function getSourceType(): AggregatorSourceType
    {
        return AggregatorSourceType::TELEGRAM;
    }

    public function getName(): string
    {
        return 'Telegram';
    }

    /**
     * @param array{name: string, username: string, type: string} $channel
     *
     * @return AggregatorResult[]
     */
    private function fetchChannel(array $channel): array
    {
        $username = $channel['username'];
        $cacheKey = self::OFFSET_CACHE_PREFIX . $username;

        $lastId = $this->cache->get($cacheKey, function (ItemInterface $item): int {
            $item->expiresAfter(self::OFFSET_TTL);

            return 0;
        });

        $messages = $this->sessionManager->getChannelHistory($username, $lastId, self::MESSAGES_LIMIT);

        $results = [];
        $maxId = $lastId;

        foreach ($messages['messages'] ?? [] as $message) {
            $messageId = $message['id'] ?? 0;
            if ($messageId > $maxId) {
                $maxId = $messageId;
            }

            // Skip messages without text
            $text = $message['message'] ?? '';
            if ($text === '') {
                continue;
            }

            // Only process messages containing URLs
            $urls = $this->extractUrls($text, $message);
            if ($urls === []) {
                continue;
            }

            $publishedAt = isset($message['date'])
                ? new \DateTimeImmutable('@' . $message['date'])
                : new \DateTimeImmutable();

            // Detect language from channel type
            $language = $this->detectLanguage($channel);

            // Use forwarded info as title if available, otherwise first line of text
            $title = $this->extractTitle($message, $text);

            foreach ($urls as $url) {
                $results[] = new AggregatorResult(
                    title: $title,
                    summary: mb_substr($text, 0, 500),
                    sourceUrl: $url,
                    sourceLanguage: $language,
                    sourceName: $channel['name'],
                    publishedAt: $publishedAt,
                    rawContent: $text,
                    keywords: [],
                    aggregatorSourceType: AggregatorSourceType::TELEGRAM,
                );
            }
        }

        // Update the offset for this channel
        if ($maxId > $lastId) {
            $this->cache->delete($cacheKey);
            $this->cache->get($cacheKey, function (ItemInterface $item) use ($maxId): int {
                $item->expiresAfter(self::OFFSET_TTL);

                return $maxId;
            });
        }

        return $results;
    }

    /**
     * Extract URLs from message text and entities.
     *
     * @return string[]
     */
    private function extractUrls(string $text, array $message): array
    {
        $urls = [];

        // Check message entities for MessageEntityUrl and MessageEntityTextUrl
        foreach ($message['entities'] ?? [] as $entity) {
            $type = $entity['_'] ?? '';

            if ($type === 'messageEntityUrl') {
                $offset = $entity['offset'] ?? 0;
                $length = $entity['length'] ?? 0;
                $url = mb_substr($text, $offset, $length);
                if ($url !== '' && filter_var($url, \FILTER_VALIDATE_URL)) {
                    $urls[] = $url;
                }
            } elseif ($type === 'messageEntityTextUrl') {
                $url = $entity['url'] ?? '';
                if ($url !== '' && filter_var($url, \FILTER_VALIDATE_URL)) {
                    $urls[] = $url;
                }
            }
        }

        // Fallback: regex on text
        if ($urls === [] && preg_match_all(self::URL_PATTERN, $text, $matches)) {
            $urls = array_filter($matches[0], fn (string $u) => filter_var($u, \FILTER_VALIDATE_URL) !== false);
        }

        return array_values(array_unique($urls));
    }

    /**
     * Extract a title from forwarded header or first line of text.
     */
    private function extractTitle(array $message, string $text): string
    {
        // If forwarded from a channel, use the channel name
        if (isset($message['fwd_from']['from_id']['channel_id'])) {
            $fwdName = $message['fwd_from']['from_name'] ?? '';
            if ($fwdName !== '') {
                return $fwdName;
            }
        }

        // Use first line of text as title
        $firstLine = strtok($text, "\n");

        return mb_substr($firstLine ?: $text, 0, 200);
    }

    /**
     * Detect language based on channel configuration.
     *
     * @param array{name: string, username: string, type: string} $channel
     */
    private function detectLanguage(array $channel): string
    {
        // Regional channels (Transnistria/Gagauzia) are typically Russian
        if ($channel['type'] === 'regional') {
            return 'ru';
        }

        // Diaspora channels vary — default to Romanian
        return 'ro';
    }
}
