<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Dto\Aggregator\RemoteContentResult;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\Readability;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches a remote URL, extracts clean article content using Readability,
 * and returns structured content for editorial review.
 */
class RemoteContentFetcher
{
    private const TIMEOUT = 10;
    private const MAX_REDIRECTS = 5;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Fetch URL and extract clean article content.
     *
     * Returns null on any failure (network, parsing, etc.).
     */
    public function fetchAndExtract(string $url): ?RemoteContentResult
    {
        try {
            $html = $this->fetchHtml($url);
            if ($html === null) {
                return null;
            }

            return $this->extractContent($html, $url);
        } catch (\Throwable $e) {
            $this->logger->warning('RemoteContentFetcher: failed', [
                'url' => mb_substr($url, 0, 100),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fetchHtml(string $url): ?string
    {
        $response = $this->httpClient->request('GET', $url, [
            'timeout' => self::TIMEOUT,
            'max_redirects' => self::MAX_REDIRECTS,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; DeschideNewsBot/1.0)',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'ro,en;q=0.9',
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            $this->logger->warning('RemoteContentFetcher: HTTP {status}', [
                'status' => $statusCode,
                'url' => mb_substr($url, 0, 100),
            ]);

            return null;
        }

        $contentType = $response->getHeaders()['content-type'][0] ?? '';
        if (!str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml')) {
            $this->logger->warning('RemoteContentFetcher: not HTML', [
                'contentType' => $contentType,
                'url' => mb_substr($url, 0, 100),
            ]);

            return null;
        }

        return $response->getContent();
    }

    private function extractContent(string $html, string $url): ?RemoteContentResult
    {
        $configuration = new Configuration();
        $configuration->setFixRelativeURLs(true);
        $configuration->setOriginalURL($url);
        $configuration->setArticleByline(true);

        $readability = new Readability($configuration);
        $readability->parse($html);

        $htmlContent = $readability->getContent();
        if ($htmlContent === null || trim(strip_tags($htmlContent)) === '') {
            $this->logger->info('RemoteContentFetcher: readability extracted no content', [
                'url' => mb_substr($url, 0, 100),
            ]);

            return null;
        }

        $textContent = trim(strip_tags($htmlContent));
        $wordCount = str_word_count($textContent);

        // Skip very short content (likely extraction failure)
        if ($wordCount < 20) {
            $this->logger->info('RemoteContentFetcher: content too short ({words} words)', [
                'words' => $wordCount,
                'url' => mb_substr($url, 0, 100),
            ]);

            return null;
        }

        return new RemoteContentResult(
            title: $readability->getTitle() ?? '',
            textContent: $textContent,
            htmlContent: $htmlContent,
            excerpt: $readability->getExcerpt(),
            siteName: $readability->getSiteName(),
            imageUrl: $readability->getImage(),
            wordCount: $wordCount,
        );
    }
}
