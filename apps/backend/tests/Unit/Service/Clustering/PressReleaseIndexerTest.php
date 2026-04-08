<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Clustering;

use App\Entity\PressRelease;
use App\Service\Clustering\PressReleaseIndexer;
use App\Service\Clustering\PressReleaseIndexManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PressReleaseIndexerTest extends TestCase
{
    private PressReleaseIndexManager&MockObject $indexManager;
    private PressReleaseIndexer $indexer;

    protected function setUp(): void
    {
        $this->indexManager = $this->createMock(PressReleaseIndexManager::class);
        $this->indexer = new PressReleaseIndexer($this->indexManager, new NullLogger());
    }

    #[Test]
    public function indexSinglePressReleaseCallsIndexDocument(): void
    {
        $pr = $this->createPressRelease(42, 'Test Title', 'Test content here');

        $this->indexManager->method('isEnabled')->willReturn(true);
        $this->indexManager->expects($this->once())
            ->method('indexDocument')
            ->with(42, $this->callback(function (array $doc): bool {
                return $doc['press_release_id'] === 42
                    && $doc['title'] === 'Test Title'
                    && str_contains($doc['content'], 'Test content');
            }));

        $this->indexer->index($pr);
    }

    #[Test]
    public function indexSkipsWhenDisabled(): void
    {
        $pr = $this->createPressRelease(1, 'Title', 'Content');

        $this->indexManager->method('isEnabled')->willReturn(false);
        $this->indexManager->expects($this->never())->method('indexDocument');

        $this->indexer->index($pr);
    }

    #[Test]
    public function indexSkipsWhenIdIsNull(): void
    {
        $pr = new PressRelease();
        $pr->setTitle('Title');
        $pr->setContent('Content');
        $pr->setCategorySlug('test');

        $this->indexManager->method('isEnabled')->willReturn(true);
        $this->indexManager->expects($this->never())->method('indexDocument');

        $this->indexer->index($pr);
    }

    #[Test]
    public function bulkIndexCallsBulkIndexOnManager(): void
    {
        $prs = [
            $this->createPressRelease(1, 'Title 1', 'Content 1'),
            $this->createPressRelease(2, 'Title 2', 'Content 2'),
        ];

        $this->indexManager->method('isEnabled')->willReturn(true);
        $this->indexManager->expects($this->once())
            ->method('bulkIndex')
            ->with($this->callback(function (array $docs): bool {
                return \count($docs) === 2
                    && isset($docs[1], $docs[2])
                    && $docs[1]['title'] === 'Title 1'
                    && $docs[2]['title'] === 'Title 2';
            }))
            ->willReturn(2);

        $result = $this->indexer->bulkIndex($prs);

        $this->assertSame(2, $result);
    }

    #[Test]
    public function bulkIndexBatchesCorrectly(): void
    {
        $prs = [];
        for ($i = 1; $i <= 5; $i++) {
            $prs[] = $this->createPressRelease($i, "Title $i", "Content $i");
        }

        $this->indexManager->method('isEnabled')->willReturn(true);
        // With batch size of 3, should call bulkIndex twice (3 + 2)
        $this->indexManager->expects($this->exactly(2))
            ->method('bulkIndex')
            ->willReturn(3, 2);

        $result = $this->indexer->bulkIndex($prs, batchSize: 3);

        $this->assertSame(5, $result);
    }

    #[Test]
    public function documentContainsAllExpectedFields(): void
    {
        $pr = $this->createPressRelease(10, 'Headline', '<p>Paragraph content</p>');
        $pr->setLead('Short lead text');
        $pr->setSourcePublisherDomain('reuters.com');
        $pr->setDetectedLanguage('en');
        $pr->setOriginalLanguage('en');

        $this->indexManager->method('isEnabled')->willReturn(true);
        $this->indexManager->expects($this->once())
            ->method('indexDocument')
            ->with(10, $this->callback(function (array $doc): bool {
                return $doc['press_release_id'] === 10
                    && $doc['title'] === 'Headline'
                    && $doc['content'] === 'Paragraph content' // HTML stripped
                    && $doc['lead'] === 'Short lead text'
                    && $doc['source_hostname'] === 'reuters.com'
                    && $doc['detected_language'] === 'en'
                    && $doc['original_language'] === 'en'
                    && isset($doc['created_at'], $doc['received_at']);
            }));

        $this->indexer->index($pr);
    }

    private function createPressRelease(int $id, string $title, string $content): PressRelease
    {
        $pr = new PressRelease();
        $pr->setTitle($title);
        $pr->setContent($content);
        $pr->setCategorySlug('extern');

        $ref = new \ReflectionProperty(PressRelease::class, 'id');
        $ref->setValue($pr, $id);

        return $pr;
    }
}
