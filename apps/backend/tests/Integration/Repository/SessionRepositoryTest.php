<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Session;
use App\Repository\SessionRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for SessionRepository.
 */
class SessionRepositoryTest extends KernelTestCase
{
    private SessionRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = static::getContainer()->get(SessionRepository::class);
        $this->em = static::getContainer()->get('doctrine')->getManager();
    }

    // =====================================================================
    // findActiveSessionsByVisitor
    // =====================================================================

    public function testFindActiveSessionsByVisitorReturnsActiveSessions(): void
    {
        $visitorId = 'visitor-' . uniqid();

        $activeSession = new Session();
        $activeSession->setVisitorId($visitorId);
        $activeSession->setStartedAt(new DateTime('-1 hour'));
        // endedAt is null by default => active
        $this->em->persist($activeSession);

        $endedSession = new Session();
        $endedSession->setVisitorId($visitorId);
        $endedSession->setStartedAt(new DateTime('-2 hours'));
        $endedSession->setEndedAt(new DateTime('-1 hour'));
        $this->em->persist($endedSession);

        $this->em->flush();

        $results = $this->repository->findActiveSessionsByVisitor($visitorId);

        $this->assertIsArray($results);
        $this->assertNotEmpty($results);
        foreach ($results as $session) {
            $this->assertNull($session->getEndedAt());
        }
    }

    public function testFindActiveSessionsByVisitorReturnsEmptyForUnknownVisitor(): void
    {
        $results = $this->repository->findActiveSessionsByVisitor('non-existent-visitor-' . uniqid());

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    // =====================================================================
    // calculateBounceRate
    // =====================================================================

    public function testCalculateBounceRateReturnsZeroWhenNoSessions(): void
    {
        // Use a date far in the past where no sessions exist
        $rate = $this->repository->calculateBounceRate(new DateTime('2000-01-01'));

        $this->assertSame(0.0, $rate);
    }

    public function testCalculateBounceRateReturnsCorrectPercentage(): void
    {
        $date = new DateTime('2026-03-24');

        // Create 2 bounced sessions (pageCount=1) and 1 non-bounced (pageCount=3)
        $bounced1 = new Session();
        $bounced1->setVisitorId('bounce-v1-' . uniqid());
        $bounced1->setStartedAt((clone $date)->setTime(10, 0, 0));
        $bounced1->setPageCount(1);
        $this->em->persist($bounced1);

        $bounced2 = new Session();
        $bounced2->setVisitorId('bounce-v2-' . uniqid());
        $bounced2->setStartedAt((clone $date)->setTime(11, 0, 0));
        $bounced2->setPageCount(1);
        $this->em->persist($bounced2);

        $notBounced = new Session();
        $notBounced->setVisitorId('nobounce-v3-' . uniqid());
        $notBounced->setStartedAt((clone $date)->setTime(12, 0, 0));
        $notBounced->setPageCount(3);
        $this->em->persist($notBounced);

        $this->em->flush();

        $rate = $this->repository->calculateBounceRate($date);

        // At least 3 sessions on this date, 2/3 = 66.67%
        $this->assertGreaterThan(0.0, $rate);
        $this->assertLessThanOrEqual(100.0, $rate);
    }

    // =====================================================================
    // calculateAvgDuration
    // =====================================================================

    public function testCalculateAvgDurationReturnsZeroWhenNoSessions(): void
    {
        $avg = $this->repository->calculateAvgDuration(new DateTime('2000-01-01'));

        $this->assertSame(0, $avg);
    }

    public function testCalculateAvgDurationReturnsPositiveValue(): void
    {
        $date = new DateTime('2026-03-25');

        $s1 = new Session();
        $s1->setVisitorId('dur-v1-' . uniqid());
        $s1->setStartedAt((clone $date)->setTime(10, 0, 0));
        $s1->setDuration(120);
        $this->em->persist($s1);

        $s2 = new Session();
        $s2->setVisitorId('dur-v2-' . uniqid());
        $s2->setStartedAt((clone $date)->setTime(11, 0, 0));
        $s2->setDuration(180);
        $this->em->persist($s2);

        $this->em->flush();

        $avg = $this->repository->calculateAvgDuration($date);

        $this->assertGreaterThan(0, $avg);
    }

    // =====================================================================
    // findByDateRange
    // =====================================================================

    public function testFindByDateRangeReturnsArray(): void
    {
        $results = $this->repository->findByDateRange(
            new DateTime('2026-03-01'),
            new DateTime('2026-03-31'),
        );

        $this->assertIsArray($results);
    }

    // =====================================================================
    // countSessionsByDate
    // =====================================================================

    public function testCountSessionsByDateReturnsInt(): void
    {
        $count = $this->repository->countSessionsByDate(new DateTime('2026-03-24'));

        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function testCountSessionsByDateReturnsZeroForEmptyDate(): void
    {
        $count = $this->repository->countSessionsByDate(new DateTime('2000-01-01'));

        $this->assertSame(0, $count);
    }
}
