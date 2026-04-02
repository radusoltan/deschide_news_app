<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\SecurityNotificationSubscriber;
use App\Service\NotificationService;
use Predis\Client as RedisClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class SecurityNotificationSubscriberTest extends TestCase
{
    private NotificationService $notificationService;
    private RedisClient $redis;
    private SecurityNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->redis = $this->createStub(RedisClient::class);
        $this->subscriber = new SecurityNotificationSubscriber(
            $this->notificationService,
            $this->redis
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = SecurityNotificationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(LoginSuccessEvent::class, $events);
        $this->assertSame('onLoginSuccess', $events[LoginSuccessEvent::class]);
    }

    public function testOnLoginSuccessSendsNotification(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getUsername')->willReturn('admin');

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        $request = $this->createStub(Request::class);
        $request->method('getClientIp')->willReturn('192.168.1.100');
        $event->method('getRequest')->willReturn($request);

        // Use __call to handle magic Redis methods
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

        $this->notificationService->expects($this->once())
            ->method('notify');

        $this->subscriber->onLoginSuccess($event);
        $this->assertTrue($setexCalled, 'setex should have been called');
    }

    public function testOnLoginSuccessSkipsWhenRateLimited(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getUsername')->willReturn('admin');

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);

        // Redis says already notified
        $this->redis->method('__call')
            ->willReturnCallback(function (string $method) {
                if ($method === 'exists') {
                    return 1;
                }

                return null;
            });

        $this->notificationService->expects($this->never())
            ->method('notify');

        $this->subscriber->onLoginSuccess($event);
    }

    public function testOnLoginSuccessSkipsNonUserEntity(): void
    {
        // Not an App\Entity\User instance
        $nonUser = $this->createStub(\Symfony\Component\Security\Core\User\UserInterface::class);

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($nonUser);

        $this->notificationService->expects($this->never())
            ->method('notify');

        $this->subscriber->onLoginSuccess($event);
    }
}
