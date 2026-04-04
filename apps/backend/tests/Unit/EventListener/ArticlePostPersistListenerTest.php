<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\Article;
use App\EventListener\ArticlePostPersistListener;
use App\Message\Editorial\IngestArticleMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ArticlePostPersistListenerTest extends TestCase
{
    private MessageBusInterface $bus;
    private ArticlePostPersistListener $listener;

    protected function setUp(): void
    {
        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->listener = new ArticlePostPersistListener($this->bus);
    }

    public function testPostPersistDispatchesIngestMessage(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(42);

        $this->bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (IngestArticleMessage $msg) {
                return $msg->articleId === 42;
            }))
            ->willReturn(new Envelope(new \stdClass()));

        $this->listener->postPersist($article);
    }
}
