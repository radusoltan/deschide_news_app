<?php

declare(strict_types=1);

namespace App\Tests\Unit\State;

use ApiPlatform\Metadata\Get;
use App\Entity\ShortLink;
use App\Repository\ShortLinkInteractionRepository;
use App\Repository\ShortLinkRepository;
use App\State\ShortLinkStats;
use App\State\ShortLinkStatsProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShortLinkStatsProviderTest extends TestCase
{
    private ShortLinkStatsProvider $provider;
    private ShortLinkRepository $shortLinkRepository;
    private ShortLinkInteractionRepository $interactionRepository;

    protected function setUp(): void
    {
        $this->shortLinkRepository = $this->createStub(ShortLinkRepository::class);
        $this->interactionRepository = $this->createStub(ShortLinkInteractionRepository::class);

        $this->provider = new ShortLinkStatsProvider(
            $this->shortLinkRepository,
            $this->interactionRepository
        );
    }

    #[Test]
    public function itReturnsStatsForExistingShortLink(): void
    {
        $shortLink = $this->createStub(ShortLink::class);

        $this->shortLinkRepository->method('find')->with(1)->willReturn($shortLink);

        $this->interactionRepository->method('getClicksPerDay')
            ->with($shortLink, 30)
            ->willReturn([['date' => '2026-03-01', 'count' => 10]]);

        $this->interactionRepository->method('getTopReferrers')
            ->with($shortLink, 10)
            ->willReturn([['referrer' => 'google.com', 'count' => 5]]);

        $this->interactionRepository->method('getDeviceTypeDistribution')
            ->with($shortLink)
            ->willReturn(['mobile' => 60, 'desktop' => 40]);

        $this->interactionRepository->method('getCountryDistribution')
            ->with($shortLink, 10)
            ->willReturn([['country' => 'MD', 'count' => 80]]);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(ShortLinkStats::class, $result);
        $this->assertSame($shortLink, $result->shortLink);
        $this->assertNotEmpty($result->clicksPerDay);
        $this->assertNotEmpty($result->topReferrers);
        $this->assertNotEmpty($result->deviceTypes);
        $this->assertNotEmpty($result->countries);
    }

    #[Test]
    public function itThrowsNotFoundWhenIdIsMissing(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Short link ID required');

        $operation = new Get();
        $this->provider->provide($operation, []);
    }

    #[Test]
    public function itThrowsNotFoundWhenShortLinkDoesNotExist(): void
    {
        $this->shortLinkRepository->method('find')->with(999)->willReturn(null);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Short link not found');

        $operation = new Get();
        $this->provider->provide($operation, ['id' => 999]);
    }

    #[Test]
    public function itReturnsStatsWithEmptyData(): void
    {
        $shortLink = $this->createStub(ShortLink::class);
        $this->shortLinkRepository->method('find')->with(1)->willReturn($shortLink);

        $this->interactionRepository->method('getClicksPerDay')->willReturn([]);
        $this->interactionRepository->method('getTopReferrers')->willReturn([]);
        $this->interactionRepository->method('getDeviceTypeDistribution')->willReturn([]);
        $this->interactionRepository->method('getCountryDistribution')->willReturn([]);

        $operation = new Get();
        $result = $this->provider->provide($operation, ['id' => 1]);

        $this->assertInstanceOf(ShortLinkStats::class, $result);
        $this->assertEmpty($result->clicksPerDay);
        $this->assertEmpty($result->topReferrers);
    }
}
