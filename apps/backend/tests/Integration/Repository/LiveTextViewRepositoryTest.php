<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\LiveText;
use App\Entity\LiveTextView;
use App\Entity\User;
use App\Repository\LiveTextViewRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for LiveTextViewRepository.
 */
class LiveTextViewRepositoryTest extends KernelTestCase
{
    private LiveTextViewRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(LiveTextViewRepository::class);
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // =====================================================================
    // findByLiveTextAndSession
    // =====================================================================

    public function testFindByLiveTextAndSessionReturnsView(): void
    {
        [$liveText, $author] = $this->createLiveText();
        $sessionId = 'session-' . uniqid();

        $view = new LiveTextView();
        $view->setLiveText($liveText);
        $view->setSessionId($sessionId);
        $view->setIpAddress('127.0.0.1');
        $this->em->persist($view);
        $this->em->flush();

        $found = $this->repository->findByLiveTextAndSession($liveText, $sessionId);

        $this->assertNotNull($found);
        $this->assertSame($sessionId, $found->getSessionId());
    }

    public function testFindByLiveTextAndSessionReturnsNullWhenNotFound(): void
    {
        [$liveText, $author] = $this->createLiveText();

        $found = $this->repository->findByLiveTextAndSession($liveText, 'nonexistent-session');

        $this->assertNull($found);
    }

    // =====================================================================
    // getTotalViewsCount
    // =====================================================================

    public function testGetTotalViewsCount(): void
    {
        [$liveText, $author] = $this->createLiveText();

        $this->createView($liveText, 'sess-a');
        $this->createView($liveText, 'sess-b');
        $this->em->flush();

        $count = $this->repository->getTotalViewsCount($liveText);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    // =====================================================================
    // getUniqueViewersCount
    // =====================================================================

    public function testGetUniqueViewersCount(): void
    {
        [$liveText, $author] = $this->createLiveText();

        // Two views from same IP, one from different IP
        $v1 = $this->createView($liveText, 'uniq-a');
        $v1->setIpAddress('10.0.0.1');

        $v2 = $this->createView($liveText, 'uniq-b');
        $v2->setIpAddress('10.0.0.1');

        $v3 = $this->createView($liveText, 'uniq-c');
        $v3->setIpAddress('10.0.0.2');

        $this->em->flush();

        $count = $this->repository->getUniqueViewersCount($liveText);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    // =====================================================================
    // getAverageTimeSpent
    // =====================================================================

    public function testGetAverageTimeSpentReturnsFloat(): void
    {
        [$liveText, $author] = $this->createLiveText();

        $v1 = $this->createView($liveText, 'time-a');
        $v1->setTimeSpent(60);

        $v2 = $this->createView($liveText, 'time-b');
        $v2->setTimeSpent(120);

        $this->em->flush();

        $avg = $this->repository->getAverageTimeSpent($liveText);

        $this->assertGreaterThan(0.0, $avg);
    }

    public function testGetAverageTimeSpentReturnsZeroWhenNoViews(): void
    {
        [$liveText, $author] = $this->createLiveText();

        $avg = $this->repository->getAverageTimeSpent($liveText);

        $this->assertSame(0.0, $avg);
    }

    // =====================================================================
    // getPeakConcurrentViewers
    // =====================================================================

    public function testGetPeakConcurrentViewersReturnsInt(): void
    {
        [$liveText, $author] = $this->createLiveText();

        $this->createView($liveText, 'peak-a');
        $this->createView($liveText, 'peak-b');
        $this->em->flush();

        $peak = $this->repository->getPeakConcurrentViewers($liveText);

        $this->assertGreaterThanOrEqual(0, $peak);
    }

    // =====================================================================
    // getViewsOverTime
    // =====================================================================

    public function testGetViewsOverTimeReturnsArray(): void
    {
        [$liveText, $author] = $this->createLiveText();

        $this->createView($liveText, 'overtime-a');
        $this->em->flush();

        $results = $this->repository->getViewsOverTime($liveText);

        $this->assertIsArray($results);
    }

    public function testGetViewsOverTimeWithSinceParameter(): void
    {
        [$liveText, $author] = $this->createLiveText();

        $this->createView($liveText, 'overtime-since-a');
        $this->em->flush();

        $results = $this->repository->getViewsOverTime($liveText, new DateTime('-1 hour'));

        $this->assertIsArray($results);
    }

    // =====================================================================
    // getActiveSessions
    // =====================================================================

    public function testGetActiveSessionsReturnsRecentViews(): void
    {
        [$liveText, $author] = $this->createLiveText();

        $active = $this->createView($liveText, 'active-sess');
        $active->setLastActivityAt(new DateTime());

        $inactive = $this->createView($liveText, 'inactive-sess');
        $inactive->setLastActivityAt(new DateTime('-1 hour'));

        $this->em->flush();

        $results = $this->repository->getActiveSessions($liveText, 5);

        $this->assertIsArray($results);
        // Active session should be included
        $sessionIds = array_map(fn (LiveTextView $v) => $v->getSessionId(), $results);
        $this->assertContains('active-sess', $sessionIds);
    }

    // =====================================================================
    // cleanupOldSessions
    // =====================================================================

    public function testCleanupOldSessionsReturnsDeletedCount(): void
    {
        $deleted = $this->repository->cleanupOldSessions(99999);

        // Should not fail; returns 0 or more
        $this->assertGreaterThanOrEqual(0, $deleted);
    }

    // =====================================================================
    // getViewersByPlatform
    // =====================================================================

    public function testGetViewersByPlatformReturnsCategories(): void
    {
        [$liveText, $author] = $this->createLiveText();

        $v1 = $this->createView($liveText, 'plat-desktop');
        $v1->setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0');

        $v2 = $this->createView($liveText, 'plat-mobile');
        $v2->setUserAgent('Mozilla/5.0 (Linux; Android 10) Mobile Safari/537.36');

        $this->em->flush();

        $results = $this->repository->getViewersByPlatform($liveText);

        $this->assertIsArray($results);
        $this->assertCount(4, $results);

        $platformNames = array_column($results, 'platform');
        $this->assertContains('Mobile', $platformNames);
        $this->assertContains('Desktop', $platformNames);
        $this->assertContains('Tablet', $platformNames);
        $this->assertContains('Other', $platformNames);
    }

    // =====================================================================
    // Helpers
    // =====================================================================

    /**
     * @return array{0: LiveText, 1: User}
     */
    private function createLiveText(): array
    {
        $suffix = uniqid('lt-', true);

        $author = new User();
        $author->setUsername('ltuser' . $suffix);
        $author->setEmail('ltuser' . $suffix . '@test.example');
        $author->setPassword('hashed');
        $this->em->persist($author);

        $liveText = new LiveText();
        $liveText->setTitle('LT Test ' . $suffix);
        $liveText->setSlug('lt-test-' . $suffix);
        $liveText->setAuthor($author);
        $this->em->persist($liveText);
        $this->em->flush();

        return [$liveText, $author];
    }

    private function createView(LiveText $liveText, string $sessionId): LiveTextView
    {
        $view = new LiveTextView();
        $view->setLiveText($liveText);
        $view->setSessionId($sessionId);
        $view->setIpAddress('127.0.0.1');
        $this->em->persist($view);

        return $view;
    }
}
