<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository\Editorial;

use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\EditorialAlignment;
use App\Repository\Editorial\VerifiedSourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * T53.1 — VerifiedSourceRepository integration tests (real DB).
 */
class VerifiedSourceRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private VerifiedSourceRepository $repository;

    /** @var list<int> */
    private array $verifiedSourceIdsToClean = [];

    /** @var list<int> */
    private array $sourceIdsToClean = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get('doctrine')->getManager();
        $this->repository = static::getContainer()->get(VerifiedSourceRepository::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->verifiedSourceIdsToClean as $id) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM verified_sources WHERE id = :id',
                ['id' => $id],
            );
        }
        foreach ($this->sourceIdsToClean as $id) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM sources WHERE id = :id',
                ['id' => $id],
            );
        }

        $this->em->close();
        parent::tearDown();
    }

    public function testFindBySlugReturnsVerifiedSource(): void
    {
        $slug = 't53r1-slug-' . uniqid();
        $vs = $this->seedVerifiedSource(
            slug: $slug,
            tier: 1,
            alignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.95',
        );

        $found = $this->repository->findBySlug($slug);

        self::assertInstanceOf(VerifiedSource::class, $found);
        self::assertSame($vs->getId(), $found->getId());
        self::assertSame($slug, $found->getSlug());
    }

    public function testFindBySlugReturnsNullWhenMissing(): void
    {
        self::assertNull($this->repository->findBySlug('t53r1-does-not-exist-' . uniqid()));
    }

    public function testFindEnabledByAlignmentFiltersAndSortsByTier(): void
    {
        $prefix = 't53r1-align-' . uniqid();

        $tier2 = $this->seedVerifiedSource(
            slug: $prefix . '-tier2',
            tier: 2,
            alignment: EditorialAlignment::INDEPENDENT_RU,
            trustScoreBaseline: '0.80',
        );
        $tier1 = $this->seedVerifiedSource(
            slug: $prefix . '-tier1',
            tier: 1,
            alignment: EditorialAlignment::INDEPENDENT_RU,
            trustScoreBaseline: '0.85',
        );
        $disabled = $this->seedVerifiedSource(
            slug: $prefix . '-disabled',
            tier: 1,
            alignment: EditorialAlignment::INDEPENDENT_RU,
            trustScoreBaseline: '0.70',
            enabled: false,
        );
        $otherAlignment = $this->seedVerifiedSource(
            slug: $prefix . '-other',
            tier: 1,
            alignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.95',
        );

        $results = $this->repository->findEnabledByAlignment(EditorialAlignment::INDEPENDENT_RU);

        $seedIds = [$tier1->getId(), $tier2->getId(), $disabled->getId(), $otherAlignment->getId()];
        $resultIds = array_values(array_filter(
            array_map(static fn (VerifiedSource $vs): ?int => $vs->getId(), $results),
            static fn (?int $id): bool => $id !== null && in_array($id, $seedIds, true),
        ));

        self::assertSame(
            [$tier1->getId(), $tier2->getId()],
            $resultIds,
            'Enabled INDEPENDENT_RU rows returned tier-ASC, disabled+other-alignment excluded.',
        );
    }

    public function testFindEnabledByAlignmentsHandlesMultipleAlignments(): void
    {
        $prefix = 't53r1-multi-' . uniqid();

        $wire = $this->seedVerifiedSource(
            slug: $prefix . '-wire',
            tier: 1,
            alignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.95',
        );
        $indepRu = $this->seedVerifiedSource(
            slug: $prefix . '-indep-ru',
            tier: 1,
            alignment: EditorialAlignment::INDEPENDENT_RU,
            trustScoreBaseline: '0.80',
        );
        $kremlin = $this->seedVerifiedSource(
            slug: $prefix . '-kremlin',
            tier: 2,
            alignment: EditorialAlignment::KREMLIN_ALIGNED,
            trustScoreBaseline: '0.60',
        );

        $results = $this->repository->findEnabledByAlignments([
            EditorialAlignment::WIRE_NEUTRAL,
            EditorialAlignment::INDEPENDENT_RU,
        ]);

        $seedIds = [$wire->getId(), $indepRu->getId(), $kremlin->getId()];
        $resultIds = array_values(array_filter(
            array_map(static fn (VerifiedSource $vs): ?int => $vs->getId(), $results),
            static fn (?int $id): bool => $id !== null && in_array($id, $seedIds, true),
        ));

        sort($resultIds);
        $expected = [$wire->getId(), $indepRu->getId()];
        sort($expected);

        self::assertSame($expected, $resultIds);
    }

    public function testFindEnabledByAlignmentsReturnsEmptyForEmptyArgument(): void
    {
        self::assertSame([], $this->repository->findEnabledByAlignments([]));
    }

    public function testFindEnabledByTierFiltersCorrectly(): void
    {
        $prefix = 't53r1-tier-' . uniqid();

        $t1 = $this->seedVerifiedSource(
            slug: $prefix . '-t1',
            tier: 1,
            alignment: EditorialAlignment::RO_MAINSTREAM,
            trustScoreBaseline: '0.85',
        );
        $t2 = $this->seedVerifiedSource(
            slug: $prefix . '-t2',
            tier: 2,
            alignment: EditorialAlignment::RO_MAINSTREAM,
            trustScoreBaseline: '0.80',
        );

        $results = $this->repository->findEnabledByTier(1);

        $seedIds = [$t1->getId(), $t2->getId()];
        $resultIds = array_values(array_filter(
            array_map(static fn (VerifiedSource $vs): ?int => $vs->getId(), $results),
            static fn (?int $id): bool => $id !== null && in_array($id, $seedIds, true),
        ));

        self::assertSame([$t1->getId()], $resultIds, 'Tier filter returns only tier-1 row among seeds.');
    }

    public function testDeleteOfLinkedSourceSetsFkToNull(): void
    {
        $source = $this->seedSource('t53r1-source-' . uniqid());
        $vs = $this->seedVerifiedSource(
            slug: 't53r1-linked-' . uniqid(),
            tier: 1,
            alignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.95',
            source: $source,
        );

        self::assertNotNull($vs->getSource());

        // Delete Source row directly; ON DELETE SET NULL must preserve VerifiedSource row.
        $sourceId = $source->getId();
        self::assertNotNull($sourceId);

        $this->em->getConnection()->executeStatement(
            'DELETE FROM sources WHERE id = :id',
            ['id' => $sourceId],
        );
        // Source already deleted, so drop it from cleanup.
        $this->sourceIdsToClean = array_values(array_filter(
            $this->sourceIdsToClean,
            static fn (int $id): bool => $id !== $sourceId,
        ));
        $this->em->clear();

        $reloadedVs = $this->repository->find($vs->getId());
        self::assertNotNull($reloadedVs, 'VerifiedSource survives Source deletion.');
        self::assertNull($reloadedVs->getSource(), 'Source FK is nulled by ON DELETE SET NULL.');
        // Delegated accessor now returns null; getName falls back to slug.
        self::assertNull($reloadedVs->getRssUrl());
        self::assertSame($reloadedVs->getSlug(), $reloadedVs->getName());
    }

    private function seedVerifiedSource(
        string $slug,
        int $tier,
        EditorialAlignment $alignment,
        string $trustScoreBaseline,
        bool $enabled = true,
        ?Source $source = null,
    ): VerifiedSource {
        $vs = new VerifiedSource(
            slug: $slug,
            tier: $tier,
            editorialAlignment: $alignment,
            trustScoreBaseline: $trustScoreBaseline,
            source: $source,
        );
        $vs->setEnabled($enabled);

        $this->em->persist($vs);
        $this->em->flush();

        $id = $vs->getId();
        self::assertNotNull($id);
        $this->verifiedSourceIdsToClean[] = $id;

        return $vs;
    }

    private function seedSource(string $name): Source
    {
        $source = new Source();
        $source->setName($name);
        $source->setRssUrl('https://example.invalid/rss');
        $source->setCredibilityWeight(0.9);
        $source->setCountry('MD');
        $source->setFetchFrequencyMinutes(60);
        $source->setIsActive(true);

        $this->em->persist($source);
        $this->em->flush();

        $id = $source->getId();
        self::assertNotNull($id);
        $this->sourceIdsToClean[] = $id;

        return $source;
    }
}
