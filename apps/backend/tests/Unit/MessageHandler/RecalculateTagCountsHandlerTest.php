<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Tag;
use App\Message\RecalculateTagCountsMessage;
use App\MessageHandler\RecalculateTagCountsHandler;
use App\Repository\TagRepository;
use App\Service\TagService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class RecalculateTagCountsHandlerTest extends TestCase
{
    private TagService $tagService;
    private TagRepository $tagRepository;
    private LoggerInterface $logger;
    private RecalculateTagCountsHandler $handler;

    protected function setUp(): void
    {
        $this->tagService = $this->createMock(TagService::class);
        $this->tagRepository = $this->createMock(TagRepository::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->handler = new RecalculateTagCountsHandler(
            $this->tagService,
            $this->tagRepository,
            $this->logger
        );
    }

    public function testInvokeWithSpecificTagId(): void
    {
        $tag = $this->createMock(Tag::class);
        $tag->method('getArticles')->willReturn(new ArrayCollection(['a', 'b', 'c']));
        $tag->method('getUsageCount')->willReturn(1);

        $tag->expects($this->once())
            ->method('setUsageCount')
            ->with(3);

        $message = new RecalculateTagCountsMessage(42);

        $this->tagRepository->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($tag);

        ($this->handler)($message);
    }

    public function testInvokeWithMissingTag(): void
    {
        $message = new RecalculateTagCountsMessage(999);

        $this->tagRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->tagService->expects($this->never())
            ->method('recalculateUsageCounts');

        ($this->handler)($message);
    }

    public function testInvokeWithNullTagIdRecalculatesAll(): void
    {
        $message = new RecalculateTagCountsMessage(null);

        $this->tagService->expects($this->once())
            ->method('recalculateUsageCounts')
            ->willReturn(50);

        ($this->handler)($message);
    }

    public function testInvokeRethrowsExceptionOnAllRecalculate(): void
    {
        $message = new RecalculateTagCountsMessage(null);

        $this->tagService->expects($this->once())
            ->method('recalculateUsageCounts')
            ->willThrowException(new \RuntimeException('DB failure'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB failure');

        ($this->handler)($message);
    }
}
