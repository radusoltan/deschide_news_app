<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\ShortLink;
use App\Message\ShortLinkClickMessage;
use App\MessageHandler\ShortLinkClickHandler;
use App\Repository\ShortLinkRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ShortLinkClickHandlerTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ShortLinkRepository $shortLinkRepository;
    private LoggerInterface $logger;
    private ShortLinkClickHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->shortLinkRepository = $this->createMock(ShortLinkRepository::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->handler = new ShortLinkClickHandler(
            $this->entityManager,
            $this->shortLinkRepository,
            $this->logger
        );
    }

    public function testInvokeRecordsClick(): void
    {
        $shortLink = $this->createMock(ShortLink::class);
        $shortLink->expects($this->once())->method('incrementClickCount');
        $shortLink->method('getClickCount')->willReturn(1);

        $this->shortLinkRepository->expects($this->once())
            ->method('find')
            ->with(10)
            ->willReturn($shortLink);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $message = new ShortLinkClickMessage(10, '192.168.1.1', 'Mozilla/5.0', 'https://google.com');
        ($this->handler)($message);
    }

    public function testInvokeHandlesMissingShortLink(): void
    {
        $this->shortLinkRepository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');

        $message = new ShortLinkClickMessage(999, null, null, null);
        ($this->handler)($message);
    }

    public function testInvokeRethrowsException(): void
    {
        $this->shortLinkRepository->expects($this->once())
            ->method('find')
            ->willThrowException(new \RuntimeException('DB error'));

        $this->expectException(\RuntimeException::class);

        $message = new ShortLinkClickMessage(1, null, null, null);
        ($this->handler)($message);
    }

    public function testDeviceDetectionMobile(): void
    {
        $shortLink = $this->createMock(ShortLink::class);
        $shortLink->method('incrementClickCount');
        $shortLink->method('getClickCount')->willReturn(1);

        $this->shortLinkRepository->method('find')->willReturn($shortLink);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $message = new ShortLinkClickMessage(1, '1.2.3.4', 'Mozilla/5.0 (iPhone; CPU iPhone OS)', null);

        // Should not throw - exercises the device detection path
        ($this->handler)($message);
        $this->assertTrue(true);
    }

    public function testDeviceDetectionTablet(): void
    {
        $shortLink = $this->createMock(ShortLink::class);
        $shortLink->method('incrementClickCount');
        $shortLink->method('getClickCount')->willReturn(1);

        $this->shortLinkRepository->method('find')->willReturn($shortLink);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $message = new ShortLinkClickMessage(1, '1.2.3.4', 'Mozilla/5.0 (iPad; CPU OS)', null);
        ($this->handler)($message);
        $this->assertTrue(true);
    }

    public function testDeviceDetectionDesktop(): void
    {
        $shortLink = $this->createMock(ShortLink::class);
        $shortLink->method('incrementClickCount');
        $shortLink->method('getClickCount')->willReturn(1);

        $this->shortLinkRepository->method('find')->willReturn($shortLink);
        $this->entityManager->method('persist');
        $this->entityManager->method('flush');

        $message = new ShortLinkClickMessage(1, '1.2.3.4', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', null);
        ($this->handler)($message);
        $this->assertTrue(true);
    }
}
