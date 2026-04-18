<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Verification;

use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\EditorialAlignment;
use App\Repository\Editorial\VerifiedSourceRepository;
use App\Repository\SourceRepository;
use App\Service\Editorial\Verification\ClaimOriginGraphBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * ADR-020 D3 critical test: the builder MUST distinguish an echo chamber
 * (N kremlin_aligned sources that cite each other) from N independent
 * chains. The VerificationGate's D3 publication matrix relies on the
 * `independentChains` counter being mechanical truth, not naive cardinality.
 */
class ClaimOriginGraphBuilderTest extends TestCase
{
    private SourceRepository&MockObject $sourceRepository;
    private VerifiedSourceRepository&MockObject $verifiedSourceRepository;
    private ClaimOriginGraphBuilder $builder;

    /** @var array<string, Source> by domain_pattern */
    private array $sourcesByDomain = [];

    /** @var array<int, VerifiedSource> */
    private array $verifiedSourcesById = [];

    /** @var list<VerifiedSource> */
    private array $allVerifiedSources = [];

    protected function setUp(): void
    {
        $this->sourceRepository = $this->createMock(SourceRepository::class);
        $this->verifiedSourceRepository = $this->createMock(VerifiedSourceRepository::class);

        // Stub SourceRepository::findAll() to return registered sources.
        $this->sourceRepository->method('findAll')
            ->willReturnCallback(fn (): array => array_values($this->sourcesByDomain));

        // Stub VerifiedSourceRepository::findOneBy(source, enabled=true).
        $this->verifiedSourceRepository->method('findOneBy')
            ->willReturnCallback(function (array $criteria): ?VerifiedSource {
                $target = $criteria['source'] ?? null;
                if (!$target instanceof Source) {
                    return null;
                }
                foreach ($this->allVerifiedSources as $vs) {
                    if ($vs->getSource() === $target) {
                        return $vs;
                    }
                }

                return null;
            });

        // Stub VerifiedSourceRepository::findBy(enabled=true) — for attribution scan.
        $this->verifiedSourceRepository->method('findBy')
            ->willReturnCallback(fn (): array => $this->allVerifiedSources);

        $this->builder = new ClaimOriginGraphBuilder(
            $this->sourceRepository,
            $this->verifiedSourceRepository,
            new NullLogger(),
        );
    }

    public function testEchoChamberCollapsesToOneChain(): void
    {
        // Registered: TASS, RIA (both kremlin_aligned).
        // Kommersant unregistered — surfaces as virtual external.
        [$tassVs, $tassSig] = $this->seedVsWithSignal(
            id: 101,
            slug: 'tass',
            domainPattern: 'tass.com',
            alignment: EditorialAlignment::KREMLIN_ALIGNED,
            tier: 2,
            title: 'Pozitia oficiala a Kremlinului',
            sourceUrl: 'https://tass.com/story/1',
            linksOut: ['https://ria.ru/story/42'],
        );

        [$riaVs, $riaSig] = $this->seedVsWithSignal(
            id: 102,
            slug: 'ria-novosti',
            domainPattern: 'ria.ru',
            alignment: EditorialAlignment::KREMLIN_ALIGNED,
            tier: 2,
            title: 'Pozitia oficiala a Kremlinului',
            sourceUrl: 'https://ria.ru/story/42',
            linksOut: ['https://www.kommersant.ru/story/77'],
        );

        [$komVs, $komSig] = $this->seedVsWithSignal(
            id: 103,
            slug: 'kommersant-virtual',
            domainPattern: 'kommersant.ru',
            alignment: EditorialAlignment::KREMLIN_ALIGNED,
            tier: 2,
            title: 'Pozitia oficiala a Kremlinului',
            sourceUrl: 'https://www.kommersant.ru/story/77',
            linksOut: ['https://tass.com/story/1'],
        );

        $graph = $this->builder->build(
            topicHash: md5('echo-claim'),
            signals: [$tassSig, $riaSig, $komSig],
        );

        $this->assertSame(
            1,
            $graph->independentChains,
            'ADR-020 D3 RED-LINE: three kremlin_aligned sources citing each other MUST collapse to 1 independent chain, not 3.',
        );
        $this->assertSame(
            ['kremlin_aligned'],
            $graph->alignmentClusters,
            'Echo chamber must report a single alignment cluster.',
        );
        $this->assertSame(3, $graph->registeredNodeCount());
    }

    public function testDiverseChainsRemainIndependent(): void
    {
        // Reuters (wire_neutral) and TASS (kremlin_aligned) with no cross-citation.
        [$reutersVs, $reutersSig] = $this->seedVsWithSignal(
            id: 201,
            slug: 'reuters',
            domainPattern: 'reuters.com',
            alignment: EditorialAlignment::WIRE_NEUTRAL,
            tier: 1,
            title: 'Summit UE confirmat',
            sourceUrl: 'https://www.reuters.com/story/100',
            linksOut: [],
        );

        [$tassVs, $tassSig] = $this->seedVsWithSignal(
            id: 202,
            slug: 'tass',
            domainPattern: 'tass.com',
            alignment: EditorialAlignment::KREMLIN_ALIGNED,
            tier: 2,
            title: 'Summit UE confirmat',
            sourceUrl: 'https://tass.com/story/200',
            linksOut: [],
        );

        $graph = $this->builder->build(
            topicHash: md5('diverse-claim'),
            signals: [$reutersSig, $tassSig],
        );

        $this->assertSame(
            2,
            $graph->independentChains,
            'ADR-020 D3 RED-LINE: two sources with no citations must remain as 2 independent chains.',
        );
        $this->assertEqualsCanonicalizing(
            ['wire_neutral', 'kremlin_aligned'],
            $graph->alignmentClusters,
            'Diverse alignments must surface as distinct alignment clusters.',
        );
    }

    public function testExternalDomainBecomesVirtualNodeAndMergesChains(): void
    {
        // Two kremlin_aligned sources both cite the same unregistered domain
        // (example.com). They must merge into 1 chain through the shared
        // virtual node.
        [$tassVs, $tassSig] = $this->seedVsWithSignal(
            id: 301,
            slug: 'tass',
            domainPattern: 'tass.com',
            alignment: EditorialAlignment::KREMLIN_ALIGNED,
            tier: 2,
            title: 'Breaking',
            sourceUrl: 'https://tass.com/story/500',
            linksOut: ['https://unknown-source.example.com/page'],
        );

        [$riaVs, $riaSig] = $this->seedVsWithSignal(
            id: 302,
            slug: 'ria-novosti',
            domainPattern: 'ria.ru',
            alignment: EditorialAlignment::KREMLIN_ALIGNED,
            tier: 2,
            title: 'Breaking',
            sourceUrl: 'https://ria.ru/story/500',
            linksOut: ['https://unknown-source.example.com/page'],
        );

        $graph = $this->builder->build(md5('ext-test'), [$tassSig, $riaSig]);

        $this->assertSame(1, $graph->independentChains);

        // Verify the virtual node is present in node list.
        $virtualNodes = array_filter(
            $graph->nodes,
            static fn (array $n): bool => \is_string($n['id']) && str_starts_with($n['id'], 'external:'),
        );
        $this->assertCount(1, $virtualNodes, 'One virtual external node for the shared domain.');
        $this->assertSame(2, $graph->registeredNodeCount(), 'registeredNodeCount excludes virtual nodes.');
    }

    public function testAttributionNameMatchCreatesEdge(): void
    {
        // Signal from Interfax that mentions "potrivit Reuters" in its
        // attribution text. Expect an edge Interfax → Reuters.
        [$reutersVs, $reutersSig] = $this->seedVsWithSignal(
            id: 401,
            slug: 'reuters',
            sourceName: 'Reuters',
            domainPattern: 'reuters.com',
            alignment: EditorialAlignment::WIRE_NEUTRAL,
            tier: 1,
            title: 'Prima relatare',
            sourceUrl: 'https://reuters.com/a',
            linksOut: [],
        );

        [$interfaxVs, $interfaxSig] = $this->seedVsWithSignal(
            id: 402,
            slug: 'interfax',
            sourceName: 'Interfax',
            domainPattern: 'interfax.com',
            alignment: EditorialAlignment::KREMLIN_ALIGNED,
            tier: 2,
            title: 'Prima relatare',
            sourceUrl: 'https://interfax.com/a',
            linksOut: [],
            attribution: 'potrivit agenției Reuters',
        );

        $graph = $this->builder->build(md5('attr-test'), [$reutersSig, $interfaxSig]);

        // One name-type edge Interfax → Reuters.
        $nameEdges = array_values(array_filter($graph->edges, static fn (array $e): bool => $e['type'] === 'name'));
        $this->assertCount(1, $nameEdges);
        $this->assertSame($interfaxSig->getVerifiedSource()->getId(), $nameEdges[0]['from']);
        $this->assertSame($reutersSig->getVerifiedSource()->getId(), $nameEdges[0]['to']);

        // After union-find, Reuters + Interfax collapse to 1 chain.
        $this->assertSame(1, $graph->independentChains);
    }

    public function testSingleSignalProducesOneChainAndOneAlignment(): void
    {
        [$reutersVs, $sig] = $this->seedVsWithSignal(
            id: 501,
            slug: 'reuters',
            domainPattern: 'reuters.com',
            alignment: EditorialAlignment::WIRE_NEUTRAL,
            tier: 1,
            title: 'Singleton',
            sourceUrl: 'https://reuters.com/x',
            linksOut: [],
        );

        $graph = $this->builder->build(md5('single'), [$sig]);

        $this->assertSame(1, $graph->independentChains);
        $this->assertSame(['wire_neutral'], $graph->alignmentClusters);
        $this->assertSame(['1' => 1], $graph->tierDistribution);
    }

    public function testToArraySerialization(): void
    {
        [$vs, $sig] = $this->seedVsWithSignal(
            id: 601,
            slug: 'reuters',
            domainPattern: 'reuters.com',
            alignment: EditorialAlignment::WIRE_NEUTRAL,
            tier: 1,
            title: 'X',
            sourceUrl: 'https://reuters.com/y',
            linksOut: [],
        );

        $graph = $this->builder->build(md5('ser-test'), [$sig]);
        $serialized = $graph->toArray();

        $this->assertArrayHasKey('topic_hash', $serialized);
        $this->assertArrayHasKey('claim_hash', $serialized);
        $this->assertArrayHasKey('nodes', $serialized);
        $this->assertArrayHasKey('edges', $serialized);
        $this->assertArrayHasKey('alignment_clusters', $serialized);
        $this->assertArrayHasKey('independent_chains', $serialized);
        $this->assertArrayHasKey('tier_distribution', $serialized);
        $this->assertSame(md5('ser-test'), $serialized['topic_hash']);
    }

    /**
     * @param list<string> $linksOut
     *
     * @return array{0: VerifiedSource, 1: SourceSignal}
     */
    private function seedVsWithSignal(
        int $id,
        string $slug,
        string $domainPattern,
        EditorialAlignment $alignment,
        int $tier,
        string $title,
        string $sourceUrl,
        array $linksOut,
        ?string $attribution = null,
        ?string $sourceName = null,
    ): array {
        $source = new Source();
        $source->setName($sourceName ?? ('Source-' . $slug));
        $source->setDomainPattern($domainPattern);

        $vs = new VerifiedSource(
            slug: $slug,
            tier: $tier,
            editorialAlignment: $alignment,
            trustScoreBaseline: '0.80',
            source: $source,
        );

        $ref = new \ReflectionClass($vs);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($vs, $id);

        $signal = new SourceSignal(
            verifiedSource: $vs,
            sourceUrl: $sourceUrl,
            title: $title,
            rawContentHash: str_repeat((string) ($id % 10), 64),
        );
        $signal->setSourceLinksOut($linksOut);
        if ($attribution !== null) {
            $signal->setSourceAttribution($attribution);
        }

        $this->sourcesByDomain[$domainPattern] = $source;
        $this->verifiedSourcesById[$id] = $vs;
        $this->allVerifiedSources[] = $vs;

        return [$vs, $signal];
    }
}
