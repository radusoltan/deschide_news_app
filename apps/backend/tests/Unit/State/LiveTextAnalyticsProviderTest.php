<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use App\Dto\LiveText\LiveTextAnalyticsDto;
use App\Entity\LiveText;
use App\Service\LiveTextAnalyticsService;
use App\State\LiveTextAnalyticsProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LiveTextAnalyticsProviderTest extends TestCase
{
    private LiveTextAnalyticsProvider $provider;
    private EntityManagerInterface $entityManager;
    private LiveTextAnalyticsService $analyticsService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->analyticsService = $this->createStub(LiveTextAnalyticsService::class);

        $this->provider = new LiveTextAnalyticsProvider(
            $this->entityManager,
            $this->analyticsService
        );
    }

    #[Test]
    public function itReturnsAnalyticsForExistingLiveText(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);

        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(1)->willReturn($liveText);

        $this->entityManager->method('getRepository')
            ->with(LiveText::class)
            ->willReturn($repo);

        $this->analyticsService->method('getAnalytics')
            ->with($liveText, false)
            ->willReturn(['totalViews' => 100, 'uniqueViewers' => 50]);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(LiveTextAnalyticsDto::class, $result);
    }

    #[Test]
    public function itReturnsNullWhenLiveTextIdMissing(): void
    {
        $operation = new Get();
        $result = $this->provider->provide($operation, []);

        $this->assertNull($result);
    }

    #[Test]
    public function itReturnsNullWhenLiveTextNotFound(): void
    {
        $repo = $this->createStub(EntityRepository::class);
        $repo->method('find')->with(999)->willReturn(null);

        $this->entityManager->method('getRepository')
            ->with(LiveText::class)
            ->willReturn($repo);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 999]);

        $this->assertNull($result);
    }
}
