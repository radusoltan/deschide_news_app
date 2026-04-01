<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\Service\NotificationFilterService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class NotificationServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;
    private SerializerInterface $serializer;
    private NotificationFilterService $filterService;
    private LoggerInterface $logger;
    private NotificationService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->serializer = $this->createStub(SerializerInterface::class);
        $this->filterService = $this->createStub(NotificationFilterService::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->service = new NotificationService(
            $this->entityManager,
            $this->httpClient,
            $this->serializer,
            $this->filterService,
            $this->logger,
            'http://localhost:3000/.well-known/mercure',
            'test-jwt-token'
        );
    }

    public function testNotifyDoesNothingWhenNoRecipients(): void
    {
        $this->filterService->method('getRecipients')->willReturn([]);

        // EntityManager should never be called if no recipients
        $this->entityManager
            ->expects($this->never())
            ->method('persist');
        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->service->notify(
            NotificationType::ARTICLE_PUBLISHED,
            'Test notification'
        );
    }

    public function testNotifyCreatesNotificationPerRecipient(): void
    {
        $user1 = $this->createStub(User::class);
        $user1->method('getUsername')->willReturn('editor1');
        $user2 = $this->createStub(User::class);
        $user2->method('getUsername')->willReturn('admin1');

        $this->filterService->method('getRecipients')->willReturn([$user1, $user2]);
        $this->serializer->method('serialize')->willReturn('{}');

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $this->httpClient->method('request')->willReturn($response);

        $persistCount = 0;
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist');
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $service = new NotificationService(
            $this->entityManager,
            $this->httpClient,
            $this->serializer,
            $this->filterService,
            $this->logger,
            'http://localhost:3000/.well-known/mercure',
            'test-jwt-token'
        );

        $service->notify(
            NotificationType::ARTICLE_PUBLISHED,
            'Article published',
            'An article was published',
            NotificationImportance::HIGH,
            'Article',
            42,
            '/admin/articles/42'
        );
    }

    public function testNotifyHandlesMercureFailureGracefully(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getUsername')->willReturn('admin');

        $this->filterService->method('getRecipients')->willReturn([$user]);
        $this->serializer->method('serialize')->willReturn('{}');

        // Mercure fails
        $this->httpClient->method('request')->willThrowException(new \Exception('Mercure unavailable'));

        // Should not throw - Mercure failure is caught
        $this->service->notify(
            NotificationType::SYSTEM_ERROR,
            'System error'
        );

        // If we reach here without exception, the test passes
        $this->assertTrue(true);
    }

    public function testNotifyWithAllParameters(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getUsername')->willReturn('admin');

        $this->filterService->method('getRecipients')->willReturn([$user]);
        $this->serializer->method('serialize')->willReturn('{"id":1}');

        $response = $this->createStub(ResponseInterface::class);
        $this->httpClient->method('request')->willReturn($response);

        $this->service->notify(
            NotificationType::JOB_FAILED,
            'Import failed',
            'The article import job failed after 50 items',
            NotificationImportance::URGENT,
            'ImportJob',
            99,
            '/admin/jobs/99'
        );

        // If we reach here, the method executed without errors
        $this->assertTrue(true);
    }
}
