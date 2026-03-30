<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\Service\NotificationService;
use Predis\Client as RedisClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final readonly class SecurityNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationService $notificationService,
        private RedisClient $redis,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Rate limiting: max 1 login notification per user per hour
        $cacheKey = \sprintf('deschide_news:notif_login:%d', $user->getId());

        if ($this->redis->exists($cacheKey)) {
            return;
        }

        // Mark as notified for 1 hour
        $this->redis->setex($cacheKey, 3600, '1');

        $ip = $event->getRequest()?->getClientIp() ?? 'necunoscut';
        $now = (new \DateTimeImmutable())->format('H:i');

        $this->notificationService->notify(
            type: NotificationType::USER_LOGIN,
            title: \sprintf('Autentificare: %s', $user->getUsername()),
            message: \sprintf('IP: %s, ora: %s', $ip, $now),
            importance: NotificationImportance::LOW,
        );
    }
}
