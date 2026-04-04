<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\Enum\NotificationType;
use App\EventSubscriber\SecurityNotificationSubscriber;
use App\Service\NotificationFilterService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Predis\Client as RedisClient;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tests for SecurityNotificationSubscriber.
 *
 * NotificationService is final, so we build a real instance.
 * We verify behavior through EntityManager and Redis mocks.
 */
class SecurityNotificationSubscriberTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private NotificationFilterService $filterService;
    private NotificationService $notificationService;
    private RedisClient $redis;
    private SecurityNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->filterService = $this->createMock(NotificationFilterService::class);
        $this->redis = $this->createStub(RedisClient::class);

        $this->notificationService = new NotificationService(
            $this->entityManager,
            $this->createStub(HttpClientInterface::class),
            $this->createStub(SerializerInterface::class),
            $this->filterService,
            new NullLogger(),
            'http://localhost:3000/.well-known/mercure',
            'test-jwt-token',
        );

        $this->subscriber = new SecurityNotificationSubscriber(
            $this->notificationService,
            $this->redis,
        );
    }

    private function buildUser(int $id, string $username): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getUsername')->willReturn($username);

        return $user;
    }

    public function testGetSubscribedEvents(): void
    {
        $events = SecurityNotificationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(LoginSuccessEvent::class, $events);
        $this->assertSame('onLoginSuccess', $events[LoginSuccessEvent::class]);
    }

    public function testOnLoginSuccessSendsNotification(): void
    {
        $user = $this->buildUser(1, 'admin');

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        $request = $this->createStub(Request::class);
        $request->method('getClientIp')->willReturn('192.168.1.100');
        $event->method('getRequest')->willReturn($request);

        // Redis: not rate-limited (key does not exist)
        $setexCalled = false;
        $this->redis->method('__call')
            ->willReturnCallback(function (string $method, array $args) use (&$setexCalled) {
                if ($method === 'exists') {
                    return 0;
                }
                if ($method === 'setex') {
                    $setexCalled = true;

                    return 'OK';
                }

                return null;
            });

        // Filter service returns a recipient so notify() persists
        $this->filterService->method('getRecipients')
            ->with(NotificationType::USER_LOGIN)
            ->willReturn([$this->buildUser(1, 'admin')]);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->subscriber->onLoginSuccess($event);
        $this->assertTrue($setexCalled, 'setex should have been called to set rate-limit key');
    }

    public function testOnLoginSuccessSkipsWhenRateLimited(): void
    {
        $user = $this->buildUser(1, 'admin');

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        // Redis says already notified (rate-limited)
        $this->redis->method('__call')
            ->willReturnCallback(function (string $method) {
                if ($method === 'exists') {
                    return 1;
                }

                return null;
            });

        // Should never persist anything when rate-limited
        $this->entityManager->expects($this->never())
            ->method('persist');
        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->subscriber->onLoginSuccess($event);
    }

    public function testOnLoginSuccessSkipsNonUserEntity(): void
    {
        // Not an App\Entity\User instance
        $nonUser = $this->createStub(\Symfony\Component\Security\Core\User\UserInterface::class);

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($nonUser);

        // Should never persist anything for non-User entities
        $this->entityManager->expects($this->never())
            ->method('persist');
        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->subscriber->onLoginSuccess($event);
    }
}
