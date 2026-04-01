<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\CleanupUnusedTagsMessage;
use App\MessageHandler\CleanupUnusedTagsHandler;
use App\Service\TagService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CleanupUnusedTagsHandlerTest extends TestCase
{
    private TagService $tagService;
    private LoggerInterface $logger;
    private CleanupUnusedTagsHandler $handler;

    protected function setUp(): void
    {
        $this->tagService = $this->createMock(TagService::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->handler = new CleanupUnusedTagsHandler($this->tagService, $this->logger);
    }

    public function testInvokeCallsCleanupWithDefaults(): void
    {
        $message = new CleanupUnusedTagsMessage();

        $this->tagService->expects($this->once())
            ->method('cleanupUnusedTags')
            ->with(30, false)
            ->willReturn(5);

        ($this->handler)($message);
    }

    public function testInvokeCallsCleanupWithCustomDaysOld(): void
    {
        $message = new CleanupUnusedTagsMessage(daysOld: 60);

        $this->tagService->expects($this->once())
            ->method('cleanupUnusedTags')
            ->with(60, false)
            ->willReturn(3);

        ($this->handler)($message);
    }

    public function testInvokeCallsCleanupDryRun(): void
    {
        $message = new CleanupUnusedTagsMessage(daysOld: 10, dryRun: true);

        $this->tagService->expects($this->once())
            ->method('cleanupUnusedTags')
            ->with(10, true)
            ->willReturn(7);

        ($this->handler)($message);
    }

    public function testInvokeRethrowsException(): void
    {
        $message = new CleanupUnusedTagsMessage();

        $this->tagService->expects($this->once())
            ->method('cleanupUnusedTags')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        ($this->handler)($message);
    }
}
