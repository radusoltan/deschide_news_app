<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Editorial;

use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Enum\Editorial\VerdictType;
use App\Message\Editorial\WriteDevelopingStoryMessage;
use App\MessageHandler\Editorial\WriteDevelopingStoryMessageHandler;
use App\Repository\ArticleRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Editorial\Writer\DevelopingStoryWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see WriteDevelopingStoryMessageHandler} (Sprint 55 T55.4).
 */
class WriteDevelopingStoryMessageHandlerTest extends TestCase
{
    private DevelopingStoryWriter&MockObject $developingStoryWriter;
    private ArticleRepository&MockObject $articleRepository;
    private SourceSignalRepository&MockObject $signalRepository;
    private LoggerInterface&MockObject $logger;
    private WriteDevelopingStoryMessageHandler $handler;

    protected function setUp(): void
    {
        $this->developingStoryWriter = $this->createMock(DevelopingStoryWriter::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->signalRepository = $this->createMock(SourceSignalRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new WriteDevelopingStoryMessageHandler(
            $this->developingStoryWriter,
            $this->articleRepository,
            $this->signalRepository,
            $this->logger,
        );
    }

    public function testHappyPathDispatchesDevelopingStoryWriter(): void
    {
        $article = new Article();
        $primary = $this->mockSignal(100);
        $sup = $this->mockSignal(101);

        $this->articleRepository->method('find')->with(42)->willReturn($article);
        $this->signalRepository->method('find')->willReturnMap([
            [100, $primary],
            [101, $sup],
        ]);

        $this->developingStoryWriter->expects($this->once())
            ->method('write')
            ->with(
                $article,
                $primary,
                [$sup],
                $this->callback(fn (VerificationVerdict $v): bool => $v->type === VerdictType::FULL_FLASH),
            )
            ->willReturn($article);

        $message = new WriteDevelopingStoryMessage(
            articleId: 42,
            primarySignalId: 100,
            supportingSignalIds: [101],
            verdictType: 'full_flash',
        );

        $result = ($this->handler)($message);

        $this->assertSame($article, $result);
    }

    public function testMissingArticleLogsAndReturnsNull(): void
    {
        $this->articleRepository->method('find')->willReturn(null);
        $this->developingStoryWriter->expects($this->never())->method('write');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('write_developing_article_missing', $this->isArray());

        $message = new WriteDevelopingStoryMessage(999, 100, [], 'full_flash');

        $this->assertNull(($this->handler)($message));
    }

    public function testMissingPrimarySignalLogsAndReturnsNull(): void
    {
        $this->articleRepository->method('find')->willReturn(new Article());
        $this->signalRepository->method('find')->willReturn(null);
        $this->developingStoryWriter->expects($this->never())->method('write');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('write_developing_primary_signal_missing', $this->isArray());

        $message = new WriteDevelopingStoryMessage(42, 999, [], 'full_flash');

        $this->assertNull(($this->handler)($message));
    }

    public function testUnknownVerdictLogsErrorAndReturnsNull(): void
    {
        $article = new Article();
        $primary = $this->mockSignal(1);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->developingStoryWriter->expects($this->never())->method('write');

        $this->logger->expects($this->once())
            ->method('error')
            ->with('write_developing_unknown_verdict_type', $this->isArray());

        $message = new WriteDevelopingStoryMessage(42, 1, [], 'garbage_verdict');

        $this->assertNull(($this->handler)($message));
    }

    public function testMissingSupportingSignalsAreDropped(): void
    {
        $article = new Article();
        $primary = $this->mockSignal(1);
        $alive = $this->mockSignal(3);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturnCallback(
            fn (int $id): ?SourceSignal => match ($id) {
                1 => $primary,
                3 => $alive,
                default => null,
            },
        );

        $this->developingStoryWriter->expects($this->once())
            ->method('write')
            ->with($article, $primary, [$alive], $this->anything())
            ->willReturn($article);

        $message = new WriteDevelopingStoryMessage(42, 1, [2, 3], 'flash_with_attribution');

        $this->assertSame($article, ($this->handler)($message));
    }

    public function testConfidencePulledFromClaimGraphSnapshot(): void
    {
        $article = new Article();
        $primary = $this->mockSignal(1);
        $primary->method('getClaimGraphSnapshot')->willReturn([
            'verdict_confidence' => 0.71,
        ]);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturn($primary);

        $captured = null;
        $this->developingStoryWriter->expects($this->once())
            ->method('write')
            ->willReturnCallback(function ($a, $p, $s, VerificationVerdict $v) use (&$captured, $article): Article {
                $captured = $v;

                return $article;
            });

        $message = new WriteDevelopingStoryMessage(42, 1, [], 'full_flash');
        ($this->handler)($message);

        $this->assertNotNull($captured);
        $this->assertEqualsWithDelta(0.71, $captured->confidence, 0.001);
    }

    private function mockSignal(int $id): SourceSignal&MockObject
    {
        $verifiedSource = $this->createMock(VerifiedSource::class);

        $signal = $this->createMock(SourceSignal::class);
        $signal->method('getId')->willReturn($id);
        $signal->method('getVerifiedSource')->willReturn($verifiedSource);

        return $signal;
    }
}
