<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\EventSubscriber\SystemNotificationSubscriber;
use App\Service\NotificationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SystemNotificationSubscriberTest extends TestCase
{
    private NotificationService $notificationService;
    private SystemNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->subscriber = new SystemNotificationSubscriber($this->notificationService);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = SystemNotificationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(WorkerMessageFailedEvent::class, $events);
        $this->assertSame('onWorkerMessageFailed', $events[WorkerMessageFailedEvent::class]);
    }

    public function testOnWorkerMessageFailedSendsNotificationWhenNotRetrying(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);
        $throwable = new \RuntimeException('Something broke');

        $event = new WorkerMessageFailedEvent($envelope, 'async', $throwable);

        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with(
                type: NotificationType::JOB_FAILED,
                title: $this->stringContains('stdClass'),
                message: $this->stringContains('Something broke'),
                importance: NotificationImportance::URGENT,
            );

        $this->subscriber->onWorkerMessageFailed($event);
    }

    public function testOnWorkerMessageFailedSkipsWhenWillRetry(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);
        $throwable = new \RuntimeException('Temporary failure');

        $event = new WorkerMessageFailedEvent($envelope, 'async', $throwable);
        $event->setForRetry();

        $this->notificationService->expects($this->never())
            ->method('notify');

        $this->subscriber->onWorkerMessageFailed($event);
    }
}
