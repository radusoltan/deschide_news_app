<?php

declare(strict_types=1);

namespace App\Tests\Unit\State\Article;

use ApiPlatform\Metadata\Delete;
use App\Entity\Article;
use App\Entity\Tag;
use App\Message\CheckOrphanedTagsMessage;
use App\Service\Editorial\ArticleCacheInvalidator;
use App\State\Article\ArticleDeleteProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ArticleDeleteProcessorTest extends TestCase
{
    private ArticleDeleteProcessor $processor;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $messageBus;
    private ArticleCacheInvalidator $cacheInvalidator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->cacheInvalidator = $this->createMock(ArticleCacheInvalidator::class);

        $this->processor = new ArticleDeleteProcessor(
            $this->entityManager,
            $this->messageBus,
            $this->cacheInvalidator,
        );
    }

    #[Test]
    public function itDeletesArticleAndFlushes(): void
    {
        $article = $this->createArticleStub(10);
        $article->method('getTags')->willReturn(new ArrayCollection());

        $this->entityManager->expects($this->once())->method('remove')->with($article);
        $this->entityManager->expects($this->once())->method('flush');

        $this->cacheInvalidator->expects($this->once())->method('invalidate')->with(10);

        $operation = new Delete();
        $result = $this->processor->process($article, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itDecrementsTagUsageCountOnDelete(): void
    {
        $tag1 = $this->createMock(Tag::class);
        $tag1->method('getId')->willReturn(1);
        $tag1->method('getUsageCount')->willReturn(5);
        $tag1->expects($this->once())->method('setUsageCount')->with(4);

        $tag2 = $this->createMock(Tag::class);
        $tag2->method('getId')->willReturn(2);
        $tag2->method('getUsageCount')->willReturn(1);
        $tag2->expects($this->once())->method('setUsageCount')->with(0);

        $article = $this->createArticleStub(10);
        $article->method('getTags')->willReturn(new ArrayCollection([$tag1, $tag2]));

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CheckOrphanedTagsMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $operation = new Delete();
        $this->processor->process($article, $operation);
    }

    #[Test]
    public function itReturnsNullForDeleteOfNonArticleData(): void
    {
        $operation = new Delete();
        $result = $this->processor->process('not-an-article', $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itDoesNotDispatchOrphanedTagsMessageWhenNoTags(): void
    {
        $article = $this->createArticleStub(10);
        $article->method('getTags')->willReturn(new ArrayCollection());

        $this->messageBus->expects($this->never())->method('dispatch');

        $operation = new Delete();
        $this->processor->process($article, $operation);
    }

    #[Test]
    public function itDeletesArticleWithoutTagsDoesNotDispatchOrphanMessage(): void
    {
        $article = $this->createArticleStub(10);
        $article->method('getTags')->willReturn(new ArrayCollection());

        $this->messageBus->expects($this->never())->method('dispatch');
        $this->entityManager->expects($this->once())->method('remove');

        $operation = new Delete();
        $result = $this->processor->process($article, $operation);

        $this->assertNull($result);
    }

    #[Test]
    public function itHandlesDeleteArticleWithNullId(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(null);
        $article->method('getTags')->willReturn(new ArrayCollection());

        $this->entityManager->expects($this->once())->method('remove');
        $this->cacheInvalidator->expects($this->never())->method('invalidate');

        $operation = new Delete();
        $result = $this->processor->process($article, $operation);

        $this->assertNull($result);
    }

    private function createArticleStub(int $id): Article&\PHPUnit\Framework\MockObject\Stub
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn($id);
        return $article;
    }
}
