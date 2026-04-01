<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Message\SessionEndEvent;
use App\MessageHandler\SessionEndHandler;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SessionEndHandlerTest extends TestCase
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private SessionEndHandler $handler;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->handler = new SessionEndHandler($this->em, $this->logger);
    }

    public function testInvokePersistsSession(): void
    {
        $message = new SessionEndEvent(
            sessionId: 'sess-123',
            visitorId: 'visitor-456',
            startedAt: new \DateTimeImmutable('2026-01-01 10:00:00'),
            endedAt: new \DateTimeImmutable('2026-01-01 10:30:00'),
            pageCount: 5,
            duration: 1800,
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
            referrer: 'https://google.com'
        );

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        ($this->handler)($message);
    }

    public function testInvokePersistsSessionWithoutOptionalFields(): void
    {
        $message = new SessionEndEvent(
            sessionId: 'sess-789',
            visitorId: 'visitor-101',
            startedAt: new \DateTimeImmutable('2026-01-01 12:00:00'),
            endedAt: new \DateTimeImmutable('2026-01-01 12:05:00'),
            pageCount: 1,
            duration: 300
        );

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        ($this->handler)($message);
    }

    public function testInvokeRethrowsException(): void
    {
        $message = new SessionEndEvent(
            sessionId: 'sess-err',
            visitorId: 'visitor-err',
            startedAt: new \DateTimeImmutable(),
            endedAt: new \DateTimeImmutable(),
            pageCount: 0,
            duration: 0
        );

        $this->em->method('persist')->willThrowException(new \RuntimeException('DB error'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DB error');

        ($this->handler)($message);
    }
}
