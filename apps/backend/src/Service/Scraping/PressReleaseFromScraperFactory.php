<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use App\Entity\PressRelease;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Service\ContentHasher;

/**
 * Creates PressRelease entities from Python scraper output items.
 *
 * Maps ScrapedItem JSON → PressRelease entity with:
 * - sourceName prefixed with "python:" for pipeline identification
 * - sourceType = SCRAPE
 * - status = PENDING (editorial gate)
 * - contentHash via ContentHasher for deduplication
 */
class PressReleaseFromScraperFactory
{
    public function __construct(
        private readonly ContentHasher $contentHasher,
    ) {}

    /**
     * Create a PressRelease from a single ScrapedItem array (parsed JSON).
     *
     * @param array{title: string, content: string, source_url: string, source_name: string, published_at: ?string, language: string, attachments: list<string>, excerpt: ?string} $item
     */
    public function create(array $item): PressRelease
    {
        $pr = new PressRelease();

        $pr->setTitle(mb_substr($item['title'], 0, 255));
        $pr->setContent($item['content']);
        $pr->setSourceUrl($item['source_url']);
        $pr->setSourceName('python:' . $item['source_name']);
        $pr->setOriginalLanguage($item['language']);
        $pr->setStatus(PressReleaseStatus::PENDING);
        $pr->setSourceType(SourceType::SCRAPE);
        $pr->setCategorySlug('general');

        // Content hash for deduplication
        $pr->setContentHash($this->contentHasher->hash($item['content']));

        // Lead from excerpt if available
        if (!empty($item['excerpt'])) {
            $pr->setLead(mb_substr($item['excerpt'], 0, 300));
        }

        // Published date
        if (!empty($item['published_at'])) {
            try {
                $publishedAt = new \DateTimeImmutable($item['published_at']);
                $pr->setReceivedAt($publishedAt);
            } catch (\Exception) {
                // Keep default (now)
            }
        }

        return $pr;
    }
}
