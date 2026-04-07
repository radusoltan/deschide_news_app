<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use App\Dto\Scraping\FeedItem;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RssFeedParser
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $scrapingUserAgent,
        private int $scrapingTimeout,
    ) {}

    /**
     * Parse an RSS/Atom feed and return items.
     *
     * @return list<FeedItem>
     */
    public function parse(string $feedUrl, string $sourceName, string $language, int $limit = 30): array
    {
        try {
            $response = $this->httpClient->request('GET', $feedUrl, [
                'headers' => ['User-Agent' => $this->scrapingUserAgent],
                'timeout' => $this->scrapingTimeout,
            ]);

            $xml = $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('RssFeedParser: failed to fetch feed', [
                'url' => $feedUrl,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return $this->parseXml($xml, $sourceName, $language, $limit);
    }

    /**
     * @return list<FeedItem>
     */
    private function parseXml(string $xml, string $sourceName, string $language, int $limit): array
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $doc = new \SimpleXMLElement($xml);
        } catch (\Exception $e) {
            $this->logger->error('RssFeedParser: invalid XML', ['error' => $e->getMessage()]);
            libxml_use_internal_errors($previous);

            return [];
        }

        $items = [];

        // RSS 2.0 format
        if (isset($doc->channel->item)) {
            foreach ($doc->channel->item as $item) {
                if (\count($items) >= $limit) {
                    break;
                }

                $feedItem = $this->parseRssItem($item, $sourceName, $language);
                if ($feedItem !== null) {
                    $items[] = $feedItem;
                }
            }
        }

        // Atom format
        if ($items === [] && isset($doc->entry)) {
            foreach ($doc->entry as $entry) {
                if (\count($items) >= $limit) {
                    break;
                }

                $feedItem = $this->parseAtomEntry($entry, $sourceName, $language);
                if ($feedItem !== null) {
                    $items[] = $feedItem;
                }
            }
        }

        libxml_use_internal_errors($previous);

        return $items;
    }

    private function parseRssItem(\SimpleXMLElement $item, string $sourceName, string $language): ?FeedItem
    {
        $title = trim((string) ($item->title ?? ''));
        $link = trim((string) ($item->link ?? ''));

        if ($title === '' || $link === '') {
            return null;
        }

        $publishedAt = null;
        $pubDate = (string) ($item->pubDate ?? '');
        if ($pubDate !== '') {
            try {
                $publishedAt = new \DateTimeImmutable($pubDate);
            } catch (\Exception) {
                // Ignore invalid dates
            }
        }

        return new FeedItem(
            title: $title,
            url: $link,
            sourceName: $sourceName,
            language: $language,
            description: trim((string) ($item->description ?? '')) ?: null,
            publishedAt: $publishedAt,
            imageUrl: $this->extractImageUrl($item),
        );
    }

    private function parseAtomEntry(\SimpleXMLElement $entry, string $sourceName, string $language): ?FeedItem
    {
        $title = trim((string) ($entry->title ?? ''));

        // Atom uses <link href="..." />
        $link = '';
        if (isset($entry->link)) {
            foreach ($entry->link as $linkEl) {
                $rel = (string) ($linkEl['rel'] ?? 'alternate');
                if ($rel === 'alternate' || $rel === '') {
                    $link = trim((string) ($linkEl['href'] ?? ''));
                    break;
                }
            }
        }

        if ($title === '' || $link === '') {
            return null;
        }

        $publishedAt = null;
        $updated = (string) ($entry->updated ?? $entry->published ?? '');
        if ($updated !== '') {
            try {
                $publishedAt = new \DateTimeImmutable($updated);
            } catch (\Exception) {
                // Ignore invalid dates
            }
        }

        $description = trim((string) ($entry->summary ?? $entry->content ?? ''));

        return new FeedItem(
            title: $title,
            url: $link,
            sourceName: $sourceName,
            language: $language,
            description: $description ?: null,
            publishedAt: $publishedAt,
            imageUrl: $this->extractImageUrl($entry),
        );
    }

    /**
     * Extract image URL from an RSS/Atom item with priority:
     * 1. <enclosure type="image/*">
     * 2. <media:content> or <media:thumbnail>
     * 3. First <img src> in <description> HTML
     */
    private function extractImageUrl(\SimpleXMLElement $item): ?string
    {
        // Priority 1: <enclosure type="image/*">
        if (isset($item->enclosure)) {
            $type = (string) $item->enclosure['type'];
            $url = (string) $item->enclosure['url'];
            if ($url !== '' && str_starts_with($type, 'image/')) {
                return $url;
            }
        }

        // Priority 2: <media:content> or <media:thumbnail>
        $namespaces = $item->getNamespaces(true);
        if (isset($namespaces['media'])) {
            $media = $item->children($namespaces['media']);
            if (isset($media->content)) {
                $url = (string) $media->content['url'];
                if ($url !== '') {
                    return $url;
                }
            }
            if (isset($media->thumbnail)) {
                $url = (string) $media->thumbnail['url'];
                if ($url !== '') {
                    return $url;
                }
            }
        }

        // Priority 3: First <img src> in <description> HTML (e.g., Gov.md)
        $description = (string) ($item->description ?? '');
        if ($description !== '' && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $description, $matches)) {
            return html_entity_decode($matches[1]);
        }

        return null;
    }
}
