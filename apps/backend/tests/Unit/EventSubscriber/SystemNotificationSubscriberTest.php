<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Enum\NotificationType;
use App\EventSubscriber\SystemNotificationSubscriber;
use App\Service\NotificationFilterService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tests for SystemNotificationSubscriber.
 *
 * NotificationService is final, so we build a real instance.
 * We verify behavior through EntityManager and FilterService mocks.
 */
class SystemNotificationSubscriberTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private NotificationFilterService $filterService;
    private NotificationService $notificationService;
    private SystemNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->filterService = $this->createMock(NotificationFilterService::class);

        $this->notificationService = new NotificationService(
            $this->entityManager,
            $this->createStub(HttpClientInterface::class),
            $this->createStub(SerializerInterface::class),
            $this->filterService,
            new NullLogger(),
            'http://localhost:3000/.well-known/mercure',
            'test-jwt-token',
        );

        $this->subscriber = new SystemNotificationSubscriber($this->notificationService, new NullLogger());
    }

    private function buildUser(int $id, string $username): \App\Entity\User
    {
        $user = $this->createStub(\App\Entity\User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getUsername')->willReturn($username);

        return $user;
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

        // Filter service returns a recipient so notify() persists
        $this->filterService->method('getRecipients')
            ->with(NotificationType::JOB_FAILED)
            ->willReturn([$this->buildUser(1, 'admin')]);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->subscriber->onWorkerMessageFailed($event);
    }

    public function testOnWorkerMessageFailedSkipsWhenWillRetry(): void
    {
        $message = new \stdClass();
        $envelope = new Envelope($message);
        $throwable = new \RuntimeException('Temporary failure');

        $event = new WorkerMessageFailedEvent($envelope, 'async', $throwable);
        $event->setForRetry();

        // Should never persist anything when will retry
        $this->entityManager->expects($this->never())
            ->method('persist');
        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->subscriber->onWorkerMessageFailed($event);
    }
}
