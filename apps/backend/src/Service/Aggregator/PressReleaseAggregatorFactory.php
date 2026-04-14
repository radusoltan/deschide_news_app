<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Entity\PressRelease;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Service\CategoryDetectorService;
use App\Service\Cleaning\SourceContentCleanerRegistry;
use App\Service\ContentHasher;

class PressReleaseAggregatorFactory
{
    public function __construct(
        private readonly CategoryDetectorService $categoryDetector,
        private readonly ContentHasher $contentHasher,
        private readonly SourceContentCleanerRegistry $sourceCleanerRegistry,
    ) {}

    public function createFromAggregatorResult(AggregatorResult $result): PressRelease
    {
        $sourceName = 'aggregator:' . $result->sourceName;

        // Apply per-source cleaning before persistence
        $cleanContent = $this->sourceCleanerRegistry->clean($sourceName, $result->rawContent);

        $pr = new PressRelease();
        $pr->setTitle(mb_substr($result->title, 0, 255));
        $pr->setContent($cleanContent);
        $pr->setLead(mb_substr($result->summary, 0, 500) ?: null);
        $pr->setSourceUrl($result->sourceUrl);
        $pr->setSourceType(SourceType::AGGREGATOR);
        $pr->setSourceName($sourceName);
        $pr->setOriginalLanguage($result->sourceLanguage);
        $pr->setStatus(PressReleaseStatus::PENDING);
        $pr->setReceivedAt($result->publishedAt);

        $categorySlug = $this->categoryDetector->detectSlug($result->title . ' ' . $cleanContent);
        $pr->setCategorySlug($categorySlug);

        $contentHash = $this->contentHasher->hash($cleanContent);
        $pr->setContentHash($contentHash);

        return $pr;
    }
}
