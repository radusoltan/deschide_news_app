<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use App\Dto\Scraping\FeedItem;
use App\Dto\Scraping\ScrapedContent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ScraperService
{
    /** @var array<string, float> Track last request time per domain for rate limiting */
    private array $lastRequestTime = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $scrapingUserAgent,
        private readonly int $scrapingTimeout,
        private readonly int $scrapingRateLimitDefault,
    ) {}

    /**
     * Scrape full content from a URL.
     */
    public function scrape(FeedItem $feedItem, int $rateLimit = 0): ?ScrapedContent
    {
        $effectiveRateLimit = $rateLimit > 0 ? $rateLimit : $this->scrapingRateLimitDefault;
        $this->respectRateLimit($feedItem->url, $effectiveRateLimit);

        $html = $this->fetchHtml($feedItem->url);
        if ($html === null) {
            return null;
        }

        // Try Trafilatura first for content extraction
        $bodyText = $this->extractWithTrafilatura($html);

        // Fallback to simple HTML stripping
        if ($bodyText === null || $bodyText === '') {
            $bodyText = $this->extractFallback($html);
        }

        if ($bodyText === '') {
            $this->logger->warning('ScraperService: no content extracted', [
                'url' => $feedItem->url,
            ]);

            return null;
        }

        return new ScrapedContent(
            url: $feedItem->url,
            title: $feedItem->title,
            bodyHtml: $html,
            bodyText: $bodyText,
            language: $feedItem->language,
            sourceName: $feedItem->sourceName,
            publishedAt: $feedItem->publishedAt,
        );
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['User-Agent' => $this->scrapingUserAgent],
                'timeout' => $this->scrapingTimeout,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $this->logger->warning('ScraperService: HTTP error', [
                    'url' => $url,
                    'status' => $statusCode,
                ]);

                return null;
            }

            return $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('ScraperService: fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function extractWithTrafilatura(string $html): ?string
    {
        $process = new Process([
            'python3', '-c',
            'import trafilatura, sys; result = trafilatura.extract(sys.stdin.read(), include_comments=False, include_tables=True, output_format="txt"); print(result or "")',
        ]);
        $process->setInput($html);
        $process->setTimeout(30);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger->debug('ScraperService: trafilatura failed, using fallback', [
                    'error' => $process->getErrorOutput(),
                ]);

                return null;
            }

            return trim($process->getOutput());
        } catch (\Throwable $e) {
            $this->logger->debug('ScraperService: trafilatura exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function extractFallback(string $html): string
    {
        // Remove script, style, nav, footer, aside elements
        $html = preg_replace('/<(script|style|nav|footer|aside|header)\b[^>]*>.*?<\/\1>/is', '', $html);

        // Strip remaining tags
        $text = strip_tags($html);

        // Normalize whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    private function respectRateLimit(string $url, int $requestsPerSecond): void
    {
        $domain = parse_url($url, \PHP_URL_HOST) ?? 'unknown';
        $now = microtime(true);
        $minInterval = 1.0 / $requestsPerSecond;

        if (isset($this->lastRequestTime[$domain])) {
            $elapsed = $now - $this->lastRequestTime[$domain];
            if ($elapsed < $minInterval) {
                $sleepMicroseconds = (int) (($minInterval - $elapsed) * 1_000_000);
                usleep($sleepMicroseconds);
            }
        }

        $this->lastRequestTime[$domain] = microtime(true);
    }
}
