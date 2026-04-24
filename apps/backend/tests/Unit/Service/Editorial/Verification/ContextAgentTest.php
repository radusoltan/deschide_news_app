<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Verification;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Dto\Agent\AgentResponse;
use App\Dto\Editorial\ClaimOriginGraph;
use App\Dto\Editorial\EditorialContext;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\EditorialAlignment;
use App\Enum\LlmModelTier;
use App\Service\Aggregator\ElasticsearchSimilarityService;
use App\Service\Ai\TierResolver;
use App\Service\Editorial\Verification\ContextAgent;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ContextAgentTest extends TestCase
{
    private ElasticsearchSimilarityService&MockObject $similarity;
    private AgentDispatcher&MockObject $dispatcher;
    private TierResolver&MockObject $tierResolver;
    private ContextAgent $agent;

    protected function setUp(): void
    {
        $this->similarity = $this->createMock(ElasticsearchSimilarityService::class);
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->tierResolver = $this->createMock(TierResolver::class);

        // Default: agent enabled, resolves to Sonnet.
        $this->tierResolver->method('isEnabled')
            ->with(ContextAgent::AGENT_ID)
            ->willReturn(true);
        $this->tierResolver->method('resolve')
            ->with(ContextAgent::AGENT_ID)
            ->willReturn(LlmModelTier::SONNET);

        $this->agent = new ContextAgent(
            $this->similarity,
            $this->dispatcher,
            $this->tierResolver,
            new NullLogger(),
        );
    }

    public function testAgentDisabledReturnsNovelPlaceholder(): void
    {
        $tierResolver = $this->createMock(TierResolver::class);
        $tierResolver->method('isEnabled')->willReturn(false);

        $agent = new ContextAgent(
            $this->similarity,
            $this->dispatcher,
            $tierResolver,
            new NullLogger(),
        );

        $this->similarity->expects($this->never())->method('findSimilar');
        $this->dispatcher->expects($this->never())->method('dispatch');

        $context = $agent->gather($this->makeGraph(), [$this->makeSignal(1, tier: 1)]);

        $this->assertTrue($context->isNovelClaim);
        $this->assertNull($context->narrativeThread);
        $this->assertSame([], $context->relatedArticles);
    }

    public function testEmptySignalsReturnsNovel(): void
    {
        $this->similarity->expects($this->never())->method('findSimilar');
        $this->dispatcher->expects($this->never())->method('dispatch');

        $context = $this->agent->gather($this->makeGraph(), []);

        $this->assertTrue($context->isNovelClaim);
    }

    public function testZeroEsHitsReturnsNovelWithoutLlmCall(): void
    {
        $this->similarity->method('findSimilar')->willReturn([]);
        $this->dispatcher->expects($this->never())->method('dispatch');

        $context = $this->agent->gather($this->makeGraph(), [$this->makeSignal(1, tier: 1)]);

        $this->assertTrue($context->isNovelClaim);
        $this->assertNull($context->narrativeThread);
        $this->assertSame([], $context->relatedArticles);
    }

    public function testEsHitsPlusSonnetSynthesisProducesContext(): void
    {
        $this->similarity->method('findSimilar')->willReturn([
            ['score' => 0.92, 'articleId' => 101, 'title' => 'Prima relatare despre scrutin'],
            ['score' => 0.85, 'articleId' => 102, 'title' => 'Analiză economică'],
            ['score' => 0.70, 'articleId' => 103, 'title' => 'Reacții politice'],
        ]);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AgentRequest $req): bool {
                $this->assertSame(ContextAgent::AGENT_ID, $req->agentId);
                $this->assertSame(LlmModelTier::SONNET, $req->tier);
                $this->assertNotNull($req->systemPrompt);
                $this->assertNull($req->tierVariant, 'ContextAgent has no variant');

                return true;
            }))
            ->willReturn($this->buildResponse(
                '{"narrative_thread":"Noile rapoarte continuă tendința de intensificare observată în ultimele săptămâni, conturând o escaladare treptată a semnalelor oficiale."}',
            ));

        $context = $this->agent->gather($this->makeGraph(), [$this->makeSignal(1, tier: 1)]);

        $this->assertFalse($context->isNovelClaim);
        $this->assertIsString($context->narrativeThread);
        $this->assertStringContainsString('tendința', $context->narrativeThread);
        $this->assertCount(3, $context->relatedArticles);
        $this->assertSame(101, $context->relatedArticles[0]['id']);
        $this->assertSame(0.92, $context->relatedArticles[0]['score']);
    }

    public function testHitsCappedAtFive(): void
    {
        $manyHits = [];
        for ($i = 1; $i <= 10; ++$i) {
            $manyHits[] = ['score' => 1.0 - ($i * 0.05), 'articleId' => $i, 'title' => 'Art ' . $i];
        }
        $this->similarity->method('findSimilar')->willReturn($manyHits);

        $this->dispatcher->method('dispatch')->willReturn(
            $this->buildResponse('{"narrative_thread":"Context."}'),
        );

        $context = $this->agent->gather($this->makeGraph(), [$this->makeSignal(1, tier: 1)]);

        $this->assertCount(5, $context->relatedArticles);
    }

    public function testSonnetFailureReturnsNullNarrativeNotNovel(): void
    {
        $this->similarity->method('findSimilar')->willReturn([
            ['score' => 0.9, 'articleId' => 1, 'title' => 'Ceva'],
        ]);

        $this->dispatcher->method('dispatch')
            ->willThrowException(new \RuntimeException('Sonnet unavailable'));

        $context = $this->agent->gather($this->makeGraph(), [$this->makeSignal(1, tier: 1)]);

        // ES hits present → NOT novel claim; but narrative synthesis failed → null.
        $this->assertFalse($context->isNovelClaim);
        $this->assertNull($context->narrativeThread);
        $this->assertCount(1, $context->relatedArticles);
    }

    public function testNonJsonSynthesisResponseYieldsNullNarrative(): void
    {
        $this->similarity->method('findSimilar')->willReturn([
            ['score' => 0.9, 'articleId' => 1, 'title' => 'Ceva'],
        ]);

        $this->dispatcher->method('dispatch')->willReturn(
            $this->buildResponse('Nu pot procesa această cerere.'),
        );

        $context = $this->agent->gather($this->makeGraph(), [$this->makeSignal(1, tier: 1)]);

        $this->assertFalse($context->isNovelClaim);
        $this->assertNull($context->narrativeThread);
    }

    public function testSystemPromptOnlyCommaBelowDiacritics(): void
    {
        $ref = new \ReflectionMethod($this->agent, 'getSystemPrompt');
        $prompt = (string) $ref->invoke($this->agent);

        $this->assertStringNotContainsString("\u{015F}", $prompt, 's-cedilla forbidden');
        $this->assertStringNotContainsString("\u{0163}", $prompt, 't-cedilla forbidden');
        $this->assertStringContainsString("\u{0219}", $prompt, 's-comma-below expected');
        $this->assertStringContainsString("\u{021B}", $prompt, 't-comma-below expected');
    }

    public function testTier1PrimarySelectionPrioritizesTier1Signal(): void
    {
        $tier2 = $this->makeSignal(1, tier: 2);
        $tier1 = $this->makeSignal(2, tier: 1);

        $this->similarity->expects($this->once())
            ->method('findSimilar')
            ->with($this->stringContains('#2'), $this->anything(), $this->anything())
            ->willReturn([]);

        $this->agent->gather($this->makeGraph(), [$tier2, $tier1]);
    }

    public function testNovelFactoryConvenience(): void
    {
        $novel = EditorialContext::novel();
        $this->assertTrue($novel->isNovelClaim);
        $this->assertSame([], $novel->relatedArticles);
        $this->assertNull($novel->narrativeThread);
    }

    /**
     * T57.P2c.1 acceptance (d): editorial.emergency_halt raised by the
     * dispatcher fails-open to null narrative. ES hits are already populated
     * before the dispatch call, so isNovelClaim stays false — halt means
     * "no narrative this cycle", not "pretend nothing was found in ES".
     */
    public function testEmergencyHaltFailsOpenToNullNarrative(): void
    {
        $this->similarity->method('findSimilar')->willReturn([
            ['score' => 0.9, 'articleId' => 1, 'title' => 'Article-from-es'],
        ]);

        $this->dispatcher->method('dispatch')
            ->willThrowException(new EmergencyHaltException(ContextAgent::AGENT_ID));

        $context = $this->agent->gather($this->makeGraph(), [$this->makeSignal(1, tier: 1)]);

        $this->assertFalse($context->isNovelClaim, 'ES hits present → not novel');
        $this->assertNull($context->narrativeThread, 'Halt resolves to null narrative');
        $this->assertCount(1, $context->relatedArticles);
    }

    private function buildResponse(string $content): AgentResponse
    {
        return new AgentResponse(
            content: $content,
            agentId: ContextAgent::AGENT_ID,
            tier: LlmModelTier::SONNET,
            model: 'claude-sonnet-4-6',
            attempts: 1,
            invocationId: '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
            metrics: null,
        );
    }

    private function makeGraph(): ClaimOriginGraph
    {
        return new ClaimOriginGraph(
            topicHash: 'topic-stub',
            claimHash: 'claim-stub',
            nodes: [],
            edges: [],
            alignmentClusters: ['wire_neutral'],
            independentChains: 1,
            tierDistribution: ['1' => 1],
        );
    }

    private function makeSignal(int $id, int $tier): SourceSignal
    {
        $source = new Source();
        $source->setName('Source-' . $id);

        $vs = new VerifiedSource(
            slug: 'stub-' . $id,
            tier: $tier,
            editorialAlignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.80',
            source: $source,
        );

        return new SourceSignal(
            verifiedSource: $vs,
            sourceUrl: 'https://example.com/' . $id,
            title: 'Titlu test #' . $id,
            rawContentHash: str_repeat((string) ($id % 10), 64),
        );
    }
}
