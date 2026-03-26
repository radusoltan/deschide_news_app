<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final readonly class SystemNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationService $notificationService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onWorkerMessageFailed',
        ];
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        // Don't notify if the message will be retried
        if ($event->willRetry()) {
            return;
        }

        $envelope = $event->getEnvelope();
        $messageClass = $envelope->getMessage()::class;
        $shortClass = basename(str_replace('\\', '/', $messageClass));
        $error = $event->getThrowable()->getMessage();

        $this->notificationService->notify(
            type: NotificationType::JOB_FAILED,
            title: \sprintf('Job eșuat: %s', $shortClass),
            message: \sprintf('Eroare: %s', mb_substr($error, 0, 500)),
            importance: NotificationImportance::URGENT,
        );
    }
}
