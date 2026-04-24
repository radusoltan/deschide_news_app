<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Topic;

use App\Entity\Topic;
use App\Enum\TopicStatus;
use App\Repository\TopicRepository;
use App\Service\NotebookLM\NotebookLMService;
use App\Service\Topic\TopicMarkdownExporter;
use App\Service\Topic\TopicNotebookSyncService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TopicNotebookSyncServiceTest extends TestCase
{
    public function testSyncTopicSkipsWhenNotebookLmUnavailable(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('isAvailable')->willReturn(false);

        $service = new TopicNotebookSyncService(
            $notebookLM,
            $this->createMock(TopicMarkdownExporter::class),
            $this->createMock(TopicRepository::class),
            $this->createMock(EntityManagerInterface::class),
            new NullLogger(),
        );

        $topic = new Topic();
        $topic->setTitle('Test');

        $result = $service->syncTopic($topic, DateRange::lastDays(30));

        self::assertTrue($result['skipped']);
        self::assertSame(0, $result['sources_added']);
    }

    public function testSyncTopicSkipsWhenNotebookCreationFails(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('isAvailable')->willReturn(true);
        $notebookLM->method('ensureNotebookForTopic')->willReturn(null);

        $service = new TopicNotebookSyncService(
            $notebookLM,
            $this->createMock(TopicMarkdownExporter::class),
            $this->createMock(TopicRepository::class),
            $this->createMock(EntityManagerInterface::class),
            new NullLogger(),
        );

        $topic = new Topic();
        $topic->setTitle('Test');

        $result = $service->syncTopic($topic, DateRange::lastDays(30));

        self::assertTrue($result['skipped']);
    }

    public function testSyncTopicDryRunDoesNotCallAddTextSource(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('isAvailable')->willReturn(true);
        $notebookLM->method('ensureNotebookForTopic')->willReturn('nb-123');
        $notebookLM->expects(self::never())->method('addTextSource');

        $exporter = $this->createMock(TopicMarkdownExporter::class);
        $exporter->method('exportSources')->willReturn([
            ['title' => 'Source 1', 'content' => 'Content 1'],
            ['title' => 'Source 2', 'content' => 'Content 2'],
        ]);

        $service = new TopicNotebookSyncService(
            $notebookLM,
            $exporter,
            $this->createMock(TopicRepository::class),
            $this->createMock(EntityManagerInterface::class),
            new NullLogger(),
        );

        $topic = new Topic();
        $topic->setTitle('Test');
        $topic->setNotebookLmId('nb-123');

        $result = $service->syncTopic($topic, DateRange::lastDays(30), dryRun: true);

        self::assertFalse($result['skipped']);
        self::assertSame(2, $result['sources_added']);
        self::assertSame('nb-123', $result['notebook_id']);
    }

    public function testSyncTopicAddsSourcesAndUpdatesCount(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('isAvailable')->willReturn(true);
        $notebookLM->method('ensureNotebookForTopic')->willReturn('nb-456');
        $notebookLM->method('addTextSource')->willReturn(true);

        $exporter = $this->createMock(TopicMarkdownExporter::class);
        $exporter->method('exportSources')->willReturn([
            ['title' => 'Source 1', 'content' => 'Content 1'],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $service = new TopicNotebookSyncService(
            $notebookLM,
            $exporter,
            $this->createMock(TopicRepository::class),
            $em,
            new NullLogger(),
        );

        $topic = new Topic();
        $topic->setTitle('Test');
        $topic->setNotebookLmId('nb-456');

        $result = $service->syncTopic($topic, DateRange::lastDays(30));

        self::assertSame(1, $result['sources_added']);
        self::assertSame(1, $topic->getNotebookSourceCount());
        self::assertNotNull($topic->getNotebookLastSyncedAt());
    }

    public function testSyncTopicRespectsMaxSourcesLimit(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('isAvailable')->willReturn(true);
        $notebookLM->method('ensureNotebookForTopic')->willReturn('nb-789');
        $notebookLM->method('addTextSource')->willReturn(true);

        $exporter = $this->createMock(TopicMarkdownExporter::class);
        $exporter->method('exportSources')->willReturn([
            ['title' => 'Source 1', 'content' => 'Content 1'],
            ['title' => 'Source 2', 'content' => 'Content 2'],
            ['title' => 'Source 3', 'content' => 'Content 3'],
        ]);

        $em = $this->createMock(EntityManagerInterface::class);

        $service = new TopicNotebookSyncService(
            $notebookLM,
            $exporter,
            $this->createMock(TopicRepository::class),
            $em,
            new NullLogger(),
        );

        $topic = new Topic();
        $topic->setTitle('Test');
        $topic->setNotebookLmId('nb-789');

        $result = $service->syncTopic($topic, DateRange::lastDays(30), maxSources: 2);

        self::assertSame(2, $result['sources_added']);
    }

    public function testSyncTopicReturnsZeroWhenNoSources(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('isAvailable')->willReturn(true);
        $notebookLM->method('ensureNotebookForTopic')->willReturn('nb-000');

        $exporter = $this->createMock(TopicMarkdownExporter::class);
        $exporter->method('exportSources')->willReturn([]);

        $service = new TopicNotebookSyncService(
            $notebookLM,
            $exporter,
            $this->createMock(TopicRepository::class),
            $this->createMock(EntityManagerInterface::class),
            new NullLogger(),
        );

        $topic = new Topic();
        $topic->setTitle('Test');
        $topic->setNotebookLmId('nb-000');

        $result = $service->syncTopic($topic, DateRange::lastDays(30));

        self::assertSame(0, $result['sources_added']);
        self::assertFalse($result['skipped']);
    }

    public function testSyncAllActiveTopicsHandlesErrors(): void
    {
        $topic1 = new Topic();
        $topic1->setTitle('Topic 1');
        $topic1->setIsActive(true);
        $topic1->setNotebookLmId('nb-1');

        $topicRepo = $this->createMock(TopicRepository::class);
        $topicRepo->method('findBy')->willReturn([$topic1]);

        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('isAvailable')->willReturn(true);
        $notebookLM->method('ensureNotebookForTopic')->willThrowException(new \RuntimeException('CLI crash'));

        $service = new TopicNotebookSyncService(
            $notebookLM,
            $this->createMock(TopicMarkdownExporter::class),
            $topicRepo,
            $this->createMock(EntityManagerInterface::class),
            new NullLogger(),
        );

        $summary = $service->syncAllActiveTopics(DateRange::lastDays(30));

        self::assertSame(0, $summary['topics_synced']);
        self::assertSame(1, $summary['errors']);
    }
}
