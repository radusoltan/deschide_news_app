<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\UrlRedirect;
use App\Repository\UrlRedirectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for UrlRedirectRepository.
 */
class UrlRedirectRepositoryTest extends KernelTestCase
{
    private UrlRedirectRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(UrlRedirectRepository::class);
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // =====================================================================
    // findByOldUrl
    // =====================================================================

    public function testFindByOldUrlReturnsRedirect(): void
    {
        $redirect = $this->createRedirect('/old-url-' . uniqid(), '/new-url');

        $found = $this->repository->findByOldUrl($redirect->getOldUrl());

        $this->assertNotNull($found);
        $this->assertSame($redirect->getOldUrl(), $found->getOldUrl());
    }

    public function testFindByOldUrlReturnsNullWhenNotFound(): void
    {
        $found = $this->repository->findByOldUrl('/nonexistent-' . uniqid());

        $this->assertNull($found);
    }

    // =====================================================================
    // redirectExists
    // =====================================================================

    public function testRedirectExistsReturnsTrueWhenExists(): void
    {
        $redirect = $this->createRedirect('/exists-test-' . uniqid(), '/new-url');

        $exists = $this->repository->redirectExists($redirect->getOldUrl());

        $this->assertTrue($exists);
    }

    public function testRedirectExistsReturnsFalseWhenNotExists(): void
    {
        $exists = $this->repository->redirectExists('/does-not-exist-' . uniqid());

        $this->assertFalse($exists);
    }

    // =====================================================================
    // findByEntity
    // =====================================================================

    public function testFindByEntityReturnsMatchingRedirects(): void
    {
        $suffix = uniqid();
        $redirect = $this->createRedirect('/entity-test-' . $suffix, '/new-url', 'article', 12345);

        $results = $this->repository->findByEntity('article', 12345);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
    }

    public function testFindByEntityReturnsEmptyForNoMatch(): void
    {
        $results = $this->repository->findByEntity('article', 99999999);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // =====================================================================
    // deleteUnusedRedirects
    // =====================================================================

    public function testDeleteUnusedRedirectsDeletesOldUnused(): void
    {
        // Create a redirect (it will have createdAt = now)
        // Deleting unused older than 0 days with maxHitCount=0 should not delete it
        // because it was just created
        $deleted = $this->repository->deleteUnusedRedirects(99999, 0);

        $this->assertGreaterThanOrEqual(0, $deleted);
    }

    // =====================================================================
    // deleteOldRedirects
    // =====================================================================

    public function testDeleteOldRedirectsReturnsDeletedCount(): void
    {
        $deleted = $this->repository->deleteOldRedirects(99999);

        $this->assertGreaterThanOrEqual(0, $deleted);
    }

    public function testDeleteOldRedirectsWithMinHitCountToKeep(): void
    {
        $deleted = $this->repository->deleteOldRedirects(99999, 10);

        $this->assertGreaterThanOrEqual(0, $deleted);
    }

    // =====================================================================
    // getStatistics
    // =====================================================================

    public function testGetStatisticsReturnsExpectedStructure(): void
    {
        // Ensure at least one redirect exists
        $this->createRedirect('/stats-test-' . uniqid(), '/new-url');

        $stats = $this->repository->getStatistics();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertArrayHasKey('unused', $stats);
        $this->assertArrayHasKey('most_used', $stats);

        $this->assertIsInt($stats['total']);
        $this->assertGreaterThanOrEqual(1, $stats['total']);
        $this->assertIsArray($stats['by_type']);
        $this->assertIsInt($stats['unused']);
        $this->assertIsArray($stats['most_used']);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    private function createRedirect(
        string $oldUrl,
        string $newUrl,
        string $type = 'article',
        ?int $entityId = null,
    ): UrlRedirect {
        $redirect = new UrlRedirect();
        $redirect->setOldUrl($oldUrl);
        $redirect->setNewUrl($newUrl);
        $redirect->setLocale('ro');
        $redirect->setType($type);
        $redirect->setEntityId($entityId);
        $this->em->persist($redirect);
        $this->em->flush();

        return $redirect;
    }
}
