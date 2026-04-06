<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Entity\PressRelease;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Service\CategoryDetectorService;
use App\Service\ContentHasher;

class PressReleaseAggregatorFactory
{
    public function __construct(
        private readonly CategoryDetectorService $categoryDetector,
        private readonly ContentHasher $contentHasher,
    ) {}

    public function createFromAggregatorResult(AggregatorResult $result): PressRelease
    {
        $pr = new PressRelease();
        $pr->setTitle(mb_substr($result->title, 0, 255));
        $pr->setContent($result->rawContent);
        $pr->setLead(mb_substr($result->summary, 0, 500) ?: null);
        $pr->setSourceUrl($result->sourceUrl);
        $pr->setSourceType(SourceType::AGGREGATOR);
        $pr->setSourceName('aggregator:' . $result->sourceName);
        $pr->setOriginalLanguage($result->sourceLanguage);
        $pr->setStatus(PressReleaseStatus::PENDING);
        $pr->setReceivedAt($result->publishedAt);

        $categorySlug = $this->categoryDetector->detectSlug($result->title . ' ' . $result->rawContent);
        $pr->setCategorySlug($categorySlug);

        $contentHash = $this->contentHasher->hash($result->rawContent);
        $pr->setContentHash($contentHash);

        return $pr;
    }
}
