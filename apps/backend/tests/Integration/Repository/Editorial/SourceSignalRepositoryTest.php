<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository\Editorial;

use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Enum\EditorialAlignment;
use App\Repository\Editorial\SourceSignalRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * T53.2 — SourceSignalRepository integration tests (real DB).
 *
 * Covers findRecentBySource, existsByHash, and the composite unique constraint
 * (verified_source_id, raw_content_hash) that powers upstream dedup.
 */
class SourceSignalRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private SourceSignalRepository $repository;

    /** @var list<int> */
    private array $signalIdsToClean = [];

    /** @var list<int> */
    private array $verifiedSourceIdsToClean = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repository = static::getContainer()->get(SourceSignalRepository::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->signalIdsToClean as $id) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM source_signals WHERE id = :id',
                ['id' => $id],
            );
        }
        foreach ($this->verifiedSourceIdsToClean as $id) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM verified_sources WHERE id = :id',
                ['id' => $id],
            );
        }

        $this->em->close();
        parent::tearDown();
    }

    public function testExistsByHashReturnsTrueOnlyForMatchingSourceAndHash(): void
    {
        $sourceA = $this->seedVerifiedSource('t53r2-src-a-' . uniqid());
        $sourceB = $this->seedVerifiedSource('t53r2-src-b-' . uniqid());

        $hash = hash('sha256', 't53r2-known-' . uniqid());

        $this->seedSignal($sourceA, $hash);

        self::assertTrue($this->repository->existsByHash($sourceA, $hash));
        self::assertFalse(
            $this->repository->existsByHash($sourceB, $hash),
            'Hash uniqueness is scoped to the source — same hash on a different source is allowed.',
        );
        self::assertFalse($this->repository->existsByHash($sourceA, hash('sha256', 'different-content')));
    }

    public function testCompositeUniqueConstraintRejectsDuplicates(): void
    {
        $source = $this->seedVerifiedSource('t53r2-unique-' . uniqid());
        $hash = hash('sha256', 't53r2-dup-' . uniqid());

        $this->seedSignal($source, $hash);

        $duplicate = new SourceSignal(
            verifiedSource: $source,
            sourceUrl: 'https://example.invalid/dup',
            title: 'Duplicate',
            rawContentHash: $hash,
        );
        $this->em->persist($duplicate);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }

    public function testSameHashAllowedOnDifferentSource(): void
    {
        $sourceA = $this->seedVerifiedSource('t53r2-cross-a-' . uniqid());
        $sourceB = $this->seedVerifiedSource('t53r2-cross-b-' . uniqid());
        $hash = hash('sha256', 't53r2-cross-' . uniqid());

        $signalA = $this->seedSignal($sourceA, $hash);
        $signalB = $this->seedSignal($sourceB, $hash);

        self::assertNotSame($signalA->getId(), $signalB->getId());
        self::assertTrue($this->repository->existsByHash($sourceA, $hash));
        self::assertTrue($this->repository->existsByHash($sourceB, $hash));
    }

    public function testFindRecentBySourceFiltersByWindowAndSortsDesc(): void
    {
        $source = $this->seedVerifiedSource('t53r2-window-' . uniqid());
        $otherSource = $this->seedVerifiedSource('t53r2-window-other-' . uniqid());

        // Captured 10h ago — within a 24h window.
        $recentSignal = $this->seedSignal(
            $source,
            hash('sha256', 'recent-' . uniqid()),
            capturedAt: new \DateTimeImmutable('-10 hours'),
        );
        // Captured 2h ago — newest, should sort first.
        $newestSignal = $this->seedSignal(
            $source,
            hash('sha256', 'newest-' . uniqid()),
            capturedAt: new \DateTimeImmutable('-2 hours'),
        );
        // Captured 50h ago — outside a 24h window.
        $oldSignal = $this->seedSignal(
            $source,
            hash('sha256', 'old-' . uniqid()),
            capturedAt: new \DateTimeImmutable('-50 hours'),
        );
        // Different source, inside window — must be excluded by source filter.
        $otherRecent = $this->seedSignal(
            $otherSource,
            hash('sha256', 'other-' . uniqid()),
            capturedAt: new \DateTimeImmutable('-1 hours'),
        );

        $results = $this->repository->findRecentBySource($source, 24);
        $resultIds = array_map(static fn (SourceSignal $s): ?int => $s->getId(), $results);

        self::assertContains($newestSignal->getId(), $resultIds);
        self::assertContains($recentSignal->getId(), $resultIds);
        self::assertNotContains($oldSignal->getId(), $resultIds);
        self::assertNotContains($otherRecent->getId(), $resultIds);

        $ourResults = array_values(array_filter(
            $results,
            static fn (SourceSignal $s): bool => in_array(
                $s->getId(),
                [$newestSignal->getId(), $recentSignal->getId()],
                true,
            ),
        ));
        self::assertSame(
            [$newestSignal->getId(), $recentSignal->getId()],
            array_map(static fn (SourceSignal $s): ?int => $s->getId(), $ourResults),
            'Results sort captured_at DESC (newest first).',
        );
    }

    private function seedVerifiedSource(string $slug): VerifiedSource
    {
        $vs = new VerifiedSource(
            slug: $slug,
            tier: 1,
            editorialAlignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.95',
        );
        $this->em->persist($vs);
        $this->em->flush();

        $id = $vs->getId();
        self::assertNotNull($id);
        $this->verifiedSourceIdsToClean[] = $id;

        return $vs;
    }

    private function seedSignal(
        VerifiedSource $source,
        string $hash,
        ?\DateTimeImmutable $capturedAt = null,
    ): SourceSignal {
        $signal = new SourceSignal(
            verifiedSource: $source,
            sourceUrl: 'https://example.invalid/' . $hash,
            title: 'Signal for ' . substr($hash, 0, 8),
            rawContentHash: $hash,
            capturedAt: $capturedAt,
        );
        $this->em->persist($signal);
        $this->em->flush();

        $id = $signal->getId();
        self::assertNotNull($id);
        $this->signalIdsToClean[] = $id;

        return $signal;
    }
}
