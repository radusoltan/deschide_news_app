<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AdminNotification;
use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class NotificationService
{
    private const TOPIC_PREFIX = 'deschide_news/admin/notifications';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HttpClientInterface $httpClient,
        private readonly SerializerInterface $serializer,
        private readonly NotificationFilterService $filterService,
        private readonly LoggerInterface $logger,
        private readonly string $mercureUrl,
        private readonly string $mercurePublisherJwt,
    ) {
    }

    public function notify(
        NotificationType $type,
        string $title,
        ?string $message = null,
        NotificationImportance $importance = NotificationImportance::MEDIUM,
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null,
        ?string $actionUrl = null,
    ): void {
        $recipients = $this->filterService->getRecipients($type);

        if (empty($recipients)) {
            $this->logger->info('No recipients for notification', [
                'type' => $type->value,
                'title' => $title,
            ]);

            return;
        }

        // Persist one notification per recipient
        $notifications = [];
        foreach ($recipients as $recipient) {
            $notification = new AdminNotification();
            $notification->setRecipientUser($recipient);
            $notification->setType($type);
            $notification->setImportance($importance);
            $notification->setTitle($title);
            $notification->setMessage($message);
            $notification->setRelatedEntityType($relatedEntityType);
            $notification->setRelatedEntityId($relatedEntityId);
            $notification->setActionUrl($actionUrl);

            $this->entityManager->persist($notification);
            $notifications[] = ['notification' => $notification, 'username' => $recipient->getUsername()];
        }

        $this->entityManager->flush();

        // Publish to Mercure for each recipient
        foreach ($notifications as $item) {
            $this->publishToMercure($item['notification'], $item['username']);
        }

        $this->logger->info('Notifications sent', [
            'type' => $type->value,
            'recipientCount' => \count($recipients),
        ]);
    }

    private function publishToMercure(AdminNotification $notification, string $username): void
    {
        try {
            $topic = \sprintf('%s/%s', self::TOPIC_PREFIX, $username);
            $data = $this->serializer->serialize($notification, 'json', [
                'groups' => ['notification:read'],
            ]);

            $this->httpClient->request('POST', $this->mercureUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->mercurePublisherJwt,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'topic' => $topic,
                    'data' => $data,
                    'private' => 'on',
                ],
            ]);
        } catch (Exception $e) {
            // Log error but don't fail — Mercure unavailability shouldn't break notifications
            $this->logger->error('Failed to publish notification to Mercure', [
                'error' => $e->getMessage(),
                'username' => $username,
                'notificationId' => (string) $notification->getId(),
            ]);
        }
    }
}
