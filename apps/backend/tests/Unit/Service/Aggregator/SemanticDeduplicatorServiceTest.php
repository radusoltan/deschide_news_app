<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Dto\DuplicateCheckResult;
use App\Enum\DeduplicationResult;
use App\Service\Aggregator\ElasticsearchSimilarityService;
use App\Service\Aggregator\SemanticDeduplicatorService;
use App\Service\ContentDeduplicator;
use App\Service\ContentHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\NullLogger;

#[CoversClass(SemanticDeduplicatorService::class)]
class SemanticDeduplicatorServiceTest extends TestCase
{
    public function testL1DuplicateByHash(): void
    {
        $hasher = new ContentHasher();
        $dedup = $this->createMock(ContentDeduplicator::class);
        $dedup->method('isDuplicate')->willReturn(
            new DuplicateCheckResult(isDuplicate: true, existingEntityType: 'Article', existingEntityId: 42)
        );

        $esSimilarity = $this->createMock(ElasticsearchSimilarityService::class);
        $esSimilarity->expects(self::never())->method('findSimilar');

        $service = new SemanticDeduplicatorService($hasher, $dedup, $esSimilarity, new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());

        $result = $service->evaluate('Test Title', '<p>Test content for hashing</p>');
        self::assertSame(DeduplicationResult::DUPLICATE, $result);
    }

    public function testL2UniqueWhenNoEsMatches(): void
    {
        $dedup = $this->createMock(ContentDeduplicator::class);
        $dedup->method('isDuplicate')->willReturn(new DuplicateCheckResult(isDuplicate: false));

        $esSimilarity = $this->createMock(ElasticsearchSimilarityService::class);
        $esSimilarity->method('isEnabled')->willReturn(true);
        $esSimilarity->method('findSimilar')->willReturn([]);

        $service = new SemanticDeduplicatorService(new ContentHasher(), $dedup, $esSimilarity, new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());

        $result = $service->evaluate('Unique article title', '<p>Completely unique content</p>');
        self::assertSame(DeduplicationResult::UNIQUE, $result);
    }

    public function testL2DuplicateHighEsScore(): void
    {
        $dedup = $this->createMock(ContentDeduplicator::class);
        $dedup->method('isDuplicate')->willReturn(new DuplicateCheckResult(isDuplicate: false));

        $esSimilarity = $this->createMock(ElasticsearchSimilarityService::class);
        $esSimilarity->method('isEnabled')->willReturn(true);
        $esSimilarity->method('findSimilar')->willReturn([
            ['score' => 0.92, 'articleId' => 100, 'title' => 'Very similar article'],
        ]);

        $service = new SemanticDeduplicatorService(new ContentHasher(), $dedup, $esSimilarity, new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());

        $result = $service->evaluate('Similar article title', '<p>Similar content</p>');
        self::assertSame(DeduplicationResult::DUPLICATE, $result);
    }

    public function testL2UniqueWhenEsDisabled(): void
    {
        $dedup = $this->createMock(ContentDeduplicator::class);
        $dedup->method('isDuplicate')->willReturn(new DuplicateCheckResult(isDuplicate: false));

        $esSimilarity = $this->createMock(ElasticsearchSimilarityService::class);
        $esSimilarity->method('isEnabled')->willReturn(false);

        $service = new SemanticDeduplicatorService(new ContentHasher(), $dedup, $esSimilarity, new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger());

        $result = $service->evaluate('Some title', '<p>Content</p>');
        self::assertSame(DeduplicationResult::UNIQUE, $result);
    }

    public function testL3NeedsReviewWhenGeminiNotAvailable(): void
    {
        $dedup = $this->createMock(ContentDeduplicator::class);
        $dedup->method('isDuplicate')->willReturn(new DuplicateCheckResult(isDuplicate: false));

        $esSimilarity = $this->createMock(ElasticsearchSimilarityService::class);
        $esSimilarity->method('isEnabled')->willReturn(true);
        // Score in gray zone (0.6-0.8) triggers L3
        $esSimilarity->method('findSimilar')->willReturn([
            ['score' => 0.72, 'articleId' => 50, 'title' => 'Somewhat similar article'],
        ]);

        // GeminiCliService mock will return empty, resulting in NEEDS_REVIEW
        $service = new SemanticDeduplicatorService(
            new ContentHasher(), $dedup, $esSimilarity, new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()), new NullLogger()
        );

        $result = $service->evaluate('Gray zone title', '<p>Gray zone content</p>');
        self::assertSame(DeduplicationResult::NEEDS_REVIEW, $result);
    }
}
