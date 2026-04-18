<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Verification;

use App\Dto\Editorial\ClaimOriginGraph;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\EditorialAlignment;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Aggregator\ElasticsearchSimilarityService;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\TierResolver;
use App\Service\Editorial\Verification\ClaimOriginGraphBuilder;
use App\Service\Editorial\Verification\SignalAggregator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SignalAggregatorTest extends TestCase
{
    private SourceSignalRepository&MockObject $signalRepo;
    private ElasticsearchSimilarityService&MockObject $similarity;
    private ClaimOriginGraphBuilder&MockObject $graphBuilder;
    private LlmRetryExecutor&MockObject $executor;
    private TierResolver&MockObject $tierResolver;
    private AppSettingRepository&MockObject $settings;
    private SignalAggregator $aggregator;

    protected function setUp(): void
    {
        $this->signalRepo = $this->createMock(SourceSignalRepository::class);
        $this->similarity = $this->createMock(ElasticsearchSimilarityService::class);
        $this->graphBuilder = $this->createMock(ClaimOriginGraphBuilder::class);
        $this->executor = $this->createMock(LlmRetryExecutor::class);
        $this->tierResolver = $this->createMock(TierResolver::class);
        $this->settings = $this->createMock(AppSettingRepository::class);

        // Default thresholds.
        $this->settings->method('get')
            ->willReturnMap([
                ['editorial.aggregator.min_es_score', null, '0.65'],
                ['editorial.aggregator.llm_gate_confidence_threshold', null, '0.7'],
            ]);
        $this->settings->method('getInt')
            ->with('editorial.aggregator.min_cluster_overlap', 2)
            ->willReturn(2);

        $this->tierResolver->method('resolve')
            ->with(SignalAggregator::AGENT_ID)
            ->willReturn(LlmModelTier::HAIKU);

        $this->aggregator = new SignalAggregator(
            $this->signalRepo,
            $this->similarity,
            $this->graphBuilder,
            $this->executor,
            $this->tierResolver,
            $this->settings,
            new NullLogger(),
        );
    }

    public function testEmptySignalIdsReturnsEmpty(): void
    {
        $result = $this->aggregator->aggregate('topic-hash', []);
        $this->assertSame([], $result);
    }

    public function testSingleSignalBypassesGateAndBuildsGraph(): void
    {
        $signal = $this->makeSignal(1);
        $this->signalRepo->method('find')->with(1)->willReturn($signal);
        $this->similarity->method('findSimilar')->willReturn([]);

        $this->tierResolver->expects($this->never())->method('isEnabled');
        $this->executor->expects($this->never())->method('executeWithRetry');

        $stubGraph = $this->makeStubGraph();
        $this->graphBuilder->expects($this->once())
            ->method('build')
            ->willReturn($stubGraph);

        $result = $this->aggregator->aggregate('topic', [1]);
        $this->assertCount(1, $result);
    }

    public function testClusterWithOverlapRunsGateAndConfirms(): void
    {
        $s1 = $this->makeSignal(1);
        $s2 = $this->makeSignal(2);

        $this->signalRepo->method('find')
            ->willReturnMap([[1, null, $s1], [2, null, $s2]]);

        // Both signals share article ids 10 and 11 (overlap=2 ≥ threshold 2).
        $this->similarity->method('findSimilar')
            ->willReturn([
                ['score' => 0.9, 'articleId' => 10, 'title' => 'A'],
                ['score' => 0.8, 'articleId' => 11, 'title' => 'B'],
            ]);

        $this->tierResolver->method('isEnabled')->willReturn(true);

        $this->executor->expects($this->once())
            ->method('executeWithRetry')
            ->willReturn([
                'content' => '{"is_same_claim":true,"confidence":0.92,"reasoning":"ambele descriu același summit"}',
                'agent_id' => 'signal_aggregator',
                'tier' => 'haiku',
                'model' => 'claude-haiku-4-5-20251001',
                'attempts' => 1,
                'fallback_detected' => false,
                'metrics' => null,
            ]);

        $this->graphBuilder->expects($this->once())
            ->method('build')
            ->willReturn($this->makeStubGraph());

        $result = $this->aggregator->aggregate('topic', [1, 2]);
        $this->assertCount(1, $result);
    }

    public function testClusterRejectedByLowConfidence(): void
    {
        $s1 = $this->makeSignal(1);
        $s2 = $this->makeSignal(2);

        $this->signalRepo->method('find')
            ->willReturnMap([[1, null, $s1], [2, null, $s2]]);

        $this->similarity->method('findSimilar')->willReturn([
            ['score' => 0.9, 'articleId' => 10, 'title' => 'A'],
            ['score' => 0.8, 'articleId' => 11, 'title' => 'B'],
        ]);

        $this->tierResolver->method('isEnabled')->willReturn(true);

        $this->executor->method('executeWithRetry')->willReturn([
            'content' => '{"is_same_claim":false,"confidence":0.20,"reasoning":"teme diferite"}',
            'agent_id' => 'signal_aggregator',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        // Gate rejection → builder NEVER called.
        $this->graphBuilder->expects($this->never())->method('build');

        $result = $this->aggregator->aggregate('topic', [1, 2]);
        $this->assertSame([], $result);
    }

    public function testLlmGateFailureIsFailOpen(): void
    {
        $s1 = $this->makeSignal(1);
        $s2 = $this->makeSignal(2);

        $this->signalRepo->method('find')
            ->willReturnMap([[1, null, $s1], [2, null, $s2]]);
        $this->similarity->method('findSimilar')->willReturn([
            ['score' => 0.9, 'articleId' => 10, 'title' => 'A'],
            ['score' => 0.8, 'articleId' => 11, 'title' => 'B'],
        ]);
        $this->tierResolver->method('isEnabled')->willReturn(true);

        $this->executor->method('executeWithRetry')
            ->willThrowException(new \RuntimeException('simulated LLM transport fail'));

        // Fail-open: graph still built from ES grouping.
        $this->graphBuilder->expects($this->once())
            ->method('build')
            ->willReturn($this->makeStubGraph());

        $result = $this->aggregator->aggregate('topic', [1, 2]);
        $this->assertCount(1, $result);
    }

    public function testAgentDisabledSkipsGateAndAcceptsBySomOverlap(): void
    {
        $s1 = $this->makeSignal(1);
        $s2 = $this->makeSignal(2);

        $this->signalRepo->method('find')
            ->willReturnMap([[1, null, $s1], [2, null, $s2]]);
        $this->similarity->method('findSimilar')->willReturn([
            ['score' => 0.9, 'articleId' => 10, 'title' => 'A'],
            ['score' => 0.8, 'articleId' => 11, 'title' => 'B'],
        ]);

        $this->tierResolver->method('isEnabled')->willReturn(false);
        $this->executor->expects($this->never())->method('executeWithRetry');

        $this->graphBuilder->expects($this->once())
            ->method('build')
            ->willReturn($this->makeStubGraph());

        $result = $this->aggregator->aggregate('topic', [1, 2]);
        $this->assertCount(1, $result);
    }

    public function testSignalsWithNoEsOverlapFormSeparateClusters(): void
    {
        $s1 = $this->makeSignal(1);
        $s2 = $this->makeSignal(2);

        $this->signalRepo->method('find')
            ->willReturnMap([[1, null, $s1], [2, null, $s2]]);

        // Each signal gets a DIFFERENT set of matched articles → no overlap.
        $this->similarity->method('findSimilar')
            ->willReturnOnConsecutiveCalls(
                [['score' => 0.9, 'articleId' => 100, 'title' => 'X'], ['score' => 0.8, 'articleId' => 101, 'title' => 'Y']],
                [['score' => 0.9, 'articleId' => 200, 'title' => 'Z'], ['score' => 0.8, 'articleId' => 201, 'title' => 'W']],
            );

        // 2 clusters, each a singleton → no LLM gate calls (single-signal bypass).
        $this->tierResolver->expects($this->never())->method('isEnabled');
        $this->executor->expects($this->never())->method('executeWithRetry');

        $this->graphBuilder->expects($this->exactly(2))
            ->method('build')
            ->willReturn($this->makeStubGraph());

        $result = $this->aggregator->aggregate('topic', [1, 2]);
        $this->assertCount(2, $result);
    }

    public function testGateNonJsonResponseTreatedAsRejection(): void
    {
        $s1 = $this->makeSignal(1);
        $s2 = $this->makeSignal(2);

        $this->signalRepo->method('find')
            ->willReturnMap([[1, null, $s1], [2, null, $s2]]);
        $this->similarity->method('findSimilar')->willReturn([
            ['score' => 0.9, 'articleId' => 10, 'title' => 'A'],
            ['score' => 0.8, 'articleId' => 11, 'title' => 'B'],
        ]);

        $this->tierResolver->method('isEnabled')->willReturn(true);
        $this->executor->method('executeWithRetry')->willReturn([
            'content' => 'Nu pot procesa această cerere.',
            'agent_id' => 'signal_aggregator',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $this->graphBuilder->expects($this->never())->method('build');

        $result = $this->aggregator->aggregate('topic', [1, 2]);
        $this->assertSame([], $result);
    }

    private function makeSignal(int $id): SourceSignal
    {
        $source = new Source();
        $source->setName('Source-' . $id);

        $vs = new VerifiedSource(
            slug: 'stub-' . $id,
            tier: 1,
            editorialAlignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.80',
            source: $source,
        );

        $ref = new \ReflectionClass($vs);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($vs, $id);

        $signal = new SourceSignal(
            verifiedSource: $vs,
            sourceUrl: 'https://example.com/' . $id,
            title: 'Titlu #' . $id,
            rawContentHash: str_repeat((string) ($id % 10), 64),
        );
        $signal->setRawSummary('Rezumat #' . $id);

        return $signal;
    }

    private function makeStubGraph(): ClaimOriginGraph
    {
        return new ClaimOriginGraph(
            topicHash: 'stub-topic',
            claimHash: 'stub-claim',
            nodes: [],
            edges: [],
            alignmentClusters: ['wire_neutral'],
            independentChains: 1,
            tierDistribution: [],
        );
    }
}
