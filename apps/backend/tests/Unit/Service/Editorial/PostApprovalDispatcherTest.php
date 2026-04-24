<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Entity\Article;
use App\Entity\PressRelease;
use App\Message\Editorial\IngestArticleMessage;
use App\Message\TranslateArticleMessage;
use App\Service\Editorial\PostApprovalDispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Unit test for {@see PostApprovalDispatcher} covering both entry paths:
 *  - PressRelease-originated (legacy)
 *  - Signal-cluster-originated (Sprint 55 T55.5 widening)
 */
class PostApprovalDispatcherTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private PostApprovalDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->dispatcher = new PostApprovalDispatcher($this->messageBus, $this->logger);
    }

    public function testDispatchWithPressReleaseDerivesLocaleFromPressRelease(): void
    {
        $article = $this->createArticleWithId(42);
        $pressRelease = $this->createMock(PressRelease::class);
        $pressRelease->method('getOriginalLanguage')->willReturn('en');

        $dispatched = [];
        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            });

        $this->dispatcher->dispatch($article, $pressRelease);

        $this->assertCount(2, $dispatched);
        $this->assertInstanceOf(TranslateArticleMessage::class, $dispatched[0]);
        $this->assertSame(42, $dispatched[0]->articleId);
        $this->assertSame(['ro', 'ru'], $dispatched[0]->locales);
        $this->assertInstanceOf(IngestArticleMessage::class, $dispatched[1]);
        $this->assertSame(42, $dispatched[1]->articleId);
    }

    public function testDispatchWithoutPressReleaseUsesFallbackLocale(): void
    {
        $article = $this->createArticleWithId(7);

        $dispatched = [];
        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            });

        $this->dispatcher->dispatch($article, null, 'ro');

        $this->assertCount(2, $dispatched);
        $this->assertInstanceOf(TranslateArticleMessage::class, $dispatched[0]);
        $this->assertSame(7, $dispatched[0]->articleId);
        $this->assertSame(['en', 'ru'], $dispatched[0]->locales);
        $this->assertInstanceOf(IngestArticleMessage::class, $dispatched[1]);
    }

    public function testDispatchWithoutPressReleaseAndRussianOriginSkipsRussianTranslation(): void
    {
        $article = $this->createArticleWithId(99);

        $dispatched = [];
        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            });

        $this->dispatcher->dispatch($article, null, 'ru');

        $this->assertSame(['ro', 'en'], $dispatched[0]->locales);
    }

    public function testDispatchDefaultsOriginalLocaleToRomanian(): void
    {
        $article = $this->createArticleWithId(11);

        $dispatched = [];
        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            });

        // Caller omits both $pressRelease and $originalLocale — must still
        // produce en+ru translations (ro is the implicit source).
        $this->dispatcher->dispatch($article);

        $this->assertSame(['en', 'ru'], $dispatched[0]->locales);
    }

    public function testDispatchAlwaysFiresTranslateBeforeIngest(): void
    {
        // Regression guard: ingestion reads translated copies in the worker;
        // if Ingest fires before Translate, the ingestion worker can race and
        // index a partially-localised article. The dispatcher must emit
        // Translate first.
        $article = $this->createArticleWithId(31);

        $dispatched = [];
        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
                $dispatched[] = $message;

                return new Envelope($message);
            });

        $this->dispatcher->dispatch($article, null, 'ro');

        $this->assertInstanceOf(TranslateArticleMessage::class, $dispatched[0]);
        $this->assertInstanceOf(IngestArticleMessage::class, $dispatched[1]);
    }

    public function testDispatchLogsSignalClusterSourceWhenPressReleaseIsNull(): void
    {
        $article = $this->createArticleWithId(77);

        $this->messageBus->method('dispatch')
            ->willReturnCallback(fn (object $m): Envelope => new Envelope($m));

        $captured = null;
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('dispatched'),
                $this->callback(function (array $context) use (&$captured): bool {
                    $captured = $context;

                    return true;
                }),
            );

        $this->dispatcher->dispatch($article, null, 'ro');

        $this->assertIsArray($captured);
        $this->assertSame('signal_cluster', $captured['source']);
        $this->assertSame('ro', $captured['originLocale']);
        $this->assertSame(77, $captured['articleId']);
    }

    private function createArticleWithId(int $id): Article
    {
        $article = new Article();
        // Article::$id is auto-generated; use reflection to emulate post-flush state.
        $ref = new \ReflectionProperty(Article::class, 'id');
        $ref->setValue($article, $id);

        return $article;
    }
}
