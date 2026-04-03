<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Article;
use App\EventListener\ArticleVaultSyncListener;
use App\Message\Editorial\SyncArticleToVaultMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticleVaultSyncListenerTest extends TestCase
{
    private MessageBusInterface $bus;
    private ArticleVaultSyncListener $listener;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->listener = new ArticleVaultSyncListener($this->bus);
    }

    public function testPostPersistDispatchesSyncMessage(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(42);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SyncArticleToVaultMessage $msg) {
                return $msg->articleId === 42 && $msg->action === 'sync';
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->listener->postPersist($article);
    }

    public function testPostUpdateDispatchesSyncMessage(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(99);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SyncArticleToVaultMessage $msg) {
                return $msg->articleId === 99 && $msg->action === 'sync';
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->listener->postUpdate($article);
    }

    public function testPreRemoveDispatchesArchiveMessage(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(77);
        $article->method('getSlug')->willReturn('my-article-slug');
        $article->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2026-04-01T10:00:00+00:00'));

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (SyncArticleToVaultMessage $msg) {
                return $msg->articleId === 77
                    && $msg->action === 'archive'
                    && $msg->slug === 'my-article-slug'
                    && $msg->createdAt !== null;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->listener->preRemove($article);
    }
}
