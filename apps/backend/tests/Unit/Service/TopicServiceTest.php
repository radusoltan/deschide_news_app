<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Article;
use App\Entity\Topic;
use App\Repository\TopicRepository;
use App\Service\TopicService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TopicServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TopicRepository&MockObject $topicRepository;
    private TopicService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->topicRepository = $this->createMock(TopicRepository::class);
        $this->service = new TopicService($this->em, $this->topicRepository);
    }

    #[Test]
    public function itCreatesATopic(): void
    {
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $topic = $this->service->createTopic('Alegeri', null, 'Procese electorale');

        $this->assertSame('Alegeri', $topic->getTitle());
        $this->assertSame('Procese electorale', $topic->getDescription());
        $this->assertNull($topic->getParent());
    }

    #[Test]
    public function itCreatesATopicWithParent(): void
    {
        $parent = new Topic();
        $parent->setTitle('Politica');

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $topic = $this->service->createTopic('Alegeri', $parent);

        $this->assertSame($parent, $topic->getParent());
    }

    #[Test]
    public function itUpdatesATopic(): void
    {
        $topic = new Topic();
        $topic->setTitle('Old Title');
        $topic->setPosition(0);

        $this->em->expects($this->once())->method('flush');

        $result = $this->service->updateTopic($topic, [
            'title' => 'New Title',
            'position' => 3,
            'isActive' => false,
        ]);

        $this->assertSame('New Title', $result->getTitle());
        $this->assertSame(3, $result->getPosition());
        $this->assertFalse($result->isActive());
    }

    #[Test]
    public function itThrowsWhenDeletingTopicWithArticles(): void
    {
        $topic = new Topic();
        $topic->setTitle('Test');

        $this->topicRepository->expects($this->once())
            ->method('getArticleCountForTopic')
            ->with($topic, false)
            ->willReturn(5);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot delete topic "Test": it has 5 associated article(s)');

        $this->service->deleteTopic($topic);
    }

    #[Test]
    public function itDeletesTopicWithNoArticles(): void
    {
        $topic = new Topic();
        $topic->setTitle('Test');

        $this->topicRepository->expects($this->once())
            ->method('getArticleCountForTopic')
            ->with($topic, false)
            ->willReturn(0);

        $this->em->expects($this->once())->method('remove')->with($topic);
        $this->em->expects($this->once())->method('flush');

        $this->service->deleteTopic($topic);
    }

    #[Test]
    public function itMovesATopic(): void
    {
        $topic = new Topic();
        $newParent = new Topic();

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $this->service->moveTopic($topic, $newParent, 2);

        $this->assertSame($newParent, $topic->getParent());
        $this->assertSame(2, $topic->getPosition());
    }

    #[Test]
    public function itSyncsArticleTopics(): void
    {
        // Create mock article with existing topic
        $existingTopic = $this->createMock(Topic::class);
        $existingTopic->method('getId')->willReturn(1);

        $article = $this->createMock(Article::class);
        $article->method('getTopics')->willReturn(new ArrayCollection([$existingTopic]));

        // Topic 1 should be removed, topic 2 should be added
        $newTopic = new Topic();
        $this->topicRepository->expects($this->once())
            ->method('find')
            ->with(2)
            ->willReturn($newTopic);

        $article->expects($this->once())->method('removeTopic')->with($existingTopic);
        $article->expects($this->once())->method('addTopic')->with($newTopic);

        $this->em->expects($this->once())->method('flush');

        $this->service->syncArticleTopics($article, [2]);
    }

    #[Test]
    public function itGetsTreeForApi(): void
    {
        $expected = [['id' => 1, 'title' => 'Politica', 'children' => []]];
        $this->topicRepository->expects($this->once())
            ->method('getFullTree')
            ->with('ro')
            ->willReturn($expected);

        $result = $this->service->getTreeForApi('ro');

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function itGetsFlatListReturnsArray(): void
    {
        $this->topicRepository->expects($this->once())
            ->method('getFullTree')
            ->with(null)
            ->willReturn([]);

        $result = $this->service->getTreeForApi(null);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
