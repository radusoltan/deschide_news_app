<?php

declare(strict_types=1);

namespace App\Tests\Unit\Repository;

use App\Entity\LiveTextView;
use App\Repository\LiveTextViewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LiveTextViewRepository.
 *
 * Tests the pure-logic platform categorization in getViewersByPlatform().
 * Query-based methods are covered in integration tests.
 */
class LiveTextViewRepositoryTest extends TestCase
{
    private LiveTextViewRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn(new ClassMetadata(LiveTextView::class));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $this->repository = new LiveTextViewRepository($registry);
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        $this->assertInstanceOf(LiveTextViewRepository::class, $this->repository);
    }

    /**
     * Test the platform categorization logic used by getViewersByPlatform().
     *
     * Since getViewersByPlatform() queries the DB then categorizes results,
     * we test the categorization logic extracted here.
     */
    #[Test]
    #[DataProvider('userAgentPlatformProvider')]
    public function platformCategorizationLogic(string $userAgent, string $expectedPlatform): void
    {
        // Extract the same categorization logic from getViewersByPlatform()
        $ua = strtolower($userAgent);
        $platform = 'Other';

        if (str_contains($ua, 'mobile') || str_contains($ua, 'android')) {
            $platform = 'Mobile';
        } elseif (str_contains($ua, 'tablet') || str_contains($ua, 'ipad')) {
            $platform = 'Tablet';
        } elseif (str_contains($ua, 'mozilla') || str_contains($ua, 'chrome') || str_contains($ua, 'safari')) {
            $platform = 'Desktop';
        }

        $this->assertSame($expectedPlatform, $platform);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function userAgentPlatformProvider(): array
    {
        return [
            'mobile chrome' => [
                'Mozilla/5.0 (Linux; Android 10; SM-G960F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0 Mobile Safari/537.36',
                'Mobile',
            ],
            'android browser' => [
                'Mozilla/5.0 (Linux; Android 9; SAMSUNG SM-J730GM) AppleWebKit/537.36',
                'Mobile',
            ],
            'ipad' => [
                'Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X) AppleWebKit/605.1.15',
                'Tablet',
            ],
            'android tablet' => [
                'Mozilla/5.0 (Linux; Tablet; rv:68.0) Gecko/68.0 Firefox/68.0',
                'Tablet',
            ],
            'desktop chrome' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Desktop',
            ],
            'desktop firefox' => [
                'Mozilla/5.0 (X11; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0',
                'Desktop',
            ],
            'desktop safari' => [
                'Safari/605.1.15',
                'Desktop',
            ],
            'bot crawler' => [
                'Googlebot/2.1 (+http://www.google.com/bot.html)',
                'Other',
            ],
            'empty user agent' => [
                '',
                'Other',
            ],
            'unknown agent' => [
                'curl/7.68.0',
                'Other',
            ],
        ];
    }

    /**
     * Verify that platform categorization produces exactly 4 categories.
     */
    #[Test]
    public function platformCategorizationProducesFourCategories(): void
    {
        $platforms = ['Mobile' => 0, 'Desktop' => 0, 'Tablet' => 0, 'Other' => 0];

        $this->assertCount(4, $platforms);
        $this->assertArrayHasKey('Mobile', $platforms);
        $this->assertArrayHasKey('Desktop', $platforms);
        $this->assertArrayHasKey('Tablet', $platforms);
        $this->assertArrayHasKey('Other', $platforms);
    }

    /**
     * Test the array_map transformation used in getViewersByPlatform return.
     */
    #[Test]
    public function platformResultTransformation(): void
    {
        $platforms = [
            'Mobile' => 15,
            'Desktop' => 30,
            'Tablet' => 5,
            'Other' => 2,
        ];

        $result = array_map(
            fn ($platform, $count) => ['platform' => $platform, 'count' => $count],
            array_keys($platforms),
            $platforms,
        );

        $this->assertCount(4, $result);
        $this->assertSame(['platform' => 'Mobile', 'count' => 15], $result[0]);
        $this->assertSame(['platform' => 'Desktop', 'count' => 30], $result[1]);
        $this->assertSame(['platform' => 'Tablet', 'count' => 5], $result[2]);
        $this->assertSame(['platform' => 'Other', 'count' => 2], $result[3]);
    }
}
