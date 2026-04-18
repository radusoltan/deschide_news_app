<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Editorial;

use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Topic;
use App\Enum\Editorial\VerdictType;
use App\Message\Editorial\WriteFlashMessage;
use App\MessageHandler\Editorial\WriteFlashMessageHandler;
use App\Repository\Editorial\SourceSignalRepository;
use App\Repository\TopicRepository;
use App\Service\Editorial\Writer\FlashWriter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see WriteFlashMessageHandler} (Sprint 55 T55.3).
 */
class WriteFlashMessageHandlerTest extends TestCase
{
    private FlashWriter&MockObject $flashWriter;
    private SourceSignalRepository&MockObject $signalRepository;
    private TopicRepository&MockObject $topicRepository;
    private LoggerInterface&MockObject $logger;
    private WriteFlashMessageHandler $handler;

    protected function setUp(): void
    {
        $this->flashWriter = $this->createMock(FlashWriter::class);
        $this->signalRepository = $this->createMock(SourceSignalRepository::class);
        $this->topicRepository = $this->createMock(TopicRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new WriteFlashMessageHandler(
            $this->flashWriter,
            $this->signalRepository,
            $this->topicRepository,
            $this->logger,
        );
    }

    public function testHappyPathDispatchesFlashWriter(): void
    {
        $primary = $this->mockSignal(100);
        $supporting1 = $this->mockSignal(101);
        $supporting2 = $this->mockSignal(102);
        $topic = $this->createMock(Topic::class);

        $this->signalRepository
            ->method('find')
            ->willReturnMap([
                [100, $primary],
                [101, $supporting1],
                [102, $supporting2],
            ]);
        $this->topicRepository->method('find')->with(55)->willReturn($topic);

        $article = new Article();
        $this->flashWriter->expects($this->once())
            ->method('write')
            ->with(
                $primary,
                [$supporting1, $supporting2],
                $this->callback(function (VerificationVerdict $v): bool {
                    return $v->type === VerdictType::FULL_FLASH;
                }),
                $topic,
            )
            ->willReturn($article);

        $message = new WriteFlashMessage(
            primarySignalId: 100,
            supportingSignalIds: [101, 102],
            verdictType: 'full_flash',
            topicId: 55,
        );

        $result = ($this->handler)($message);

        $this->assertSame($article, $result);
    }

    public function testMissingPrimarySignalLogsWarningAndReturnsNull(): void
    {
        $this->signalRepository->method('find')->willReturn(null);
        $this->flashWriter->expects($this->never())->method('write');
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('write_flash_primary_signal_missing', $this->isArray());

        $message = new WriteFlashMessage(999, [], 'full_flash');

        $this->assertNull(($this->handler)($message));
    }

    public function testUnknownVerdictTypeLogsErrorAndReturnsNull(): void
    {
        $primary = $this->mockSignal(1);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->flashWriter->expects($this->never())->method('write');
        $this->logger->expects($this->once())
            ->method('error')
            ->with('write_flash_unknown_verdict_type', $this->isArray());

        $message = new WriteFlashMessage(1, [], 'nonsense_verdict_type');

        $this->assertNull(($this->handler)($message));
    }

    public function testMissingSupportingSignalsAreSilentlyDropped(): void
    {
        // Supporting signals can be archived/deleted between dispatch and consumption;
        // writer must still produce an Article from the primary + surviving supporting.
        $primary = $this->mockSignal(1);
        $surviving = $this->mockSignal(3);

        $this->signalRepository->method('find')->willReturnCallback(
            fn (int $id) => match ($id) {
                1 => $primary,
                3 => $surviving,
                default => null, // 2 has been archived
            },
        );

        $this->flashWriter->expects($this->once())
            ->method('write')
            ->with($primary, [$surviving], $this->anything(), null)
            ->willReturn(new Article());

        $message = new WriteFlashMessage(1, [2, 3], 'flash_with_attribution');

        $this->assertInstanceOf(Article::class, ($this->handler)($message));
    }

    public function testConfidenceExtractedFromClaimGraphSnapshot(): void
    {
        $primary = $this->mockSignal(1);
        $primary->method('getClaimGraphSnapshot')->willReturn([
            'verdict_confidence' => 0.87,
            'verdict' => 'full_flash',
        ]);

        $this->signalRepository->method('find')->willReturn($primary);

        $capturedVerdict = null;
        $this->flashWriter->expects($this->once())
            ->method('write')
            ->willReturnCallback(function ($p, $sup, VerificationVerdict $v) use (&$capturedVerdict): Article {
                $capturedVerdict = $v;

                return new Article();
            });

        $message = new WriteFlashMessage(1, [], 'full_flash');

        ($this->handler)($message);

        $this->assertNotNull($capturedVerdict);
        $this->assertEqualsWithDelta(0.87, $capturedVerdict->confidence, 0.001);
    }

    public function testTopicIdNullResolvesToNull(): void
    {
        $primary = $this->mockSignal(1);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->topicRepository->expects($this->never())->method('find');

        $this->flashWriter->expects($this->once())
            ->method('write')
            ->with($primary, [], $this->anything(), null)
            ->willReturn(new Article());

        $message = new WriteFlashMessage(1, [], 'full_flash', topicId: null);

        ($this->handler)($message);
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
