<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Editorial;

use App\Dto\Editorial\ClaimOriginGraph;
use App\Entity\Editorial\SourceSignal;
use App\Message\Editorial\AggregateSignalsMessage;
use App\Message\Editorial\VerifyClaimMessage;
use App\MessageHandler\Editorial\AggregateSignalsMessageHandler;
use App\Repository\AppSettingRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Editorial\TopicHashResolver;
use App\Service\Editorial\Verification\SignalAggregator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Unit test for {@see AggregateSignalsMessageHandler} covering the
 * Sprint 56 T56.02 emergency_halt circuit breaker + baseline delegation.
 */
class AggregateSignalsMessageHandlerTest extends TestCase
{
    private SignalAggregator&MockObject $aggregator;
    private MessageBusInterface&MockObject $messageBus;
    private AppSettingRepository&MockObject $appSettings;
    private SourceSignalRepository&MockObject $signalRepository;
    private TopicHashResolver&MockObject $topicHashResolver;

    /** @var list<object> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->aggregator = $this->createMock(SignalAggregator::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);
        $this->signalRepository = $this->createMock(SourceSignalRepository::class);
        $this->topicHashResolver = $this->createMock(TopicHashResolver::class);

        // Default: emergency_halt OFF — existing delegation path runs.
        $this->appSettings->method('getBool')
            ->with('editorial.emergency_halt', false)
            ->willReturn(false);

        $this->dispatched = [];
        $this->messageBus->method('dispatch')
            ->willReturnCallback(function (object $m): Envelope {
                $this->dispatched[] = $m;

                return new Envelope($m);
            });
    }

    public function testEmergencyHaltShortCircuitsBeforeAggregatorIsInvoked(): void
    {
        $haltedAppSettings = $this->createMock(AppSettingRepository::class);
        $haltedAppSettings->method('getBool')
            ->with('editorial.emergency_halt', false)
            ->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);

        $handler = new AggregateSignalsMessageHandler(
            $this->aggregator,
            $this->messageBus,
            $haltedAppSettings,
            $this->signalRepository,
            $this->topicHashResolver,
            $logger,
        );

        // Short-circuit must happen BEFORE any aggregator, resolver, or dispatch call.
        $this->aggregator->expects($this->never())->method('aggregate');
        $this->topicHashResolver->expects($this->never())->method('resolve');
        $this->signalRepository->expects($this->never())->method('find');
        $this->messageBus->expects($this->never())->method('dispatch');

        $logger->expects($this->once())
            ->method('info')
            ->with('emergency_halt.triggered', $this->callback(static function (array $ctx): bool {
                return ($ctx['handler'] ?? null) === AggregateSignalsMessageHandler::class
                    && ($ctx['message_class'] ?? null) === AggregateSignalsMessage::class
                    && ($ctx['message_id_hint'] ?? null) === 'topic-halted';
            }));

        $handler(new AggregateSignalsMessage(topicHash: 'topic-halted', signalIds: [1, 2, 3]));

        $this->assertSame([], $this->dispatched);
    }

    public function testEmergencyHaltDisabledDelegatesToAggregatorAndDispatchesVerify(): void
    {
        // Sanity twin: with flag=false (setUp default), the aggregator runs
        // and each confirmed graph produces a VerifyClaimMessage dispatch.
        $handler = $this->makeHandler();

        $graph = $this->makeGraph('topic-normal');
        $signal = $this->makeSignal(10);

        $this->signalRepository->method('find')
            ->with(10)
            ->willReturn($signal);

        $this->topicHashResolver->expects($this->once())
            ->method('resolve')
            ->with('topic-normal', $signal)
            ->willReturn(null); // exercise S55 fallback path

        $this->aggregator->expects($this->once())
            ->method('aggregate')
            ->with('topic-normal', [10, 20])
            ->willReturn([$graph]);

        $handler(new AggregateSignalsMessage(topicHash: 'topic-normal', signalIds: [10, 20]));

        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(VerifyClaimMessage::class, $this->dispatched[0]);
    }

    public function testDispatchesVerifyClaimMessageWithResolvedTopicId(): void
    {
        // T56.04 core behaviour: a hydratable representative signal reaches
        // the resolver, the resolver returns a concrete topic id, and that
        // id rides along on every VerifyClaimMessage in the batch.
        $handler = $this->makeHandler();

        $signal = $this->makeSignal(10);
        $this->signalRepository->method('find')
            ->with(10)
            ->willReturn($signal);

        $this->topicHashResolver->expects($this->once())
            ->method('resolve')
            ->with('topic-live', $signal)
            ->willReturn(42);

        $graphA = $this->makeGraph('topic-live', 'claim-a');
        $graphB = $this->makeGraph('topic-live', 'claim-b');
        $this->aggregator->method('aggregate')
            ->with('topic-live', [10, 20])
            ->willReturn([$graphA, $graphB]);

        $handler(new AggregateSignalsMessage(topicHash: 'topic-live', signalIds: [10, 20]));

        $this->assertCount(2, $this->dispatched);
        foreach ($this->dispatched as $msg) {
            $this->assertInstanceOf(VerifyClaimMessage::class, $msg);
            $this->assertSame(42, $msg->topicId, 'resolved topicId must ride on every VerifyClaimMessage in the batch');
        }
    }

    public function testDispatchesVerifyClaimMessageWithNullTopicIdOnUnresolvable(): void
    {
        // Resolver returns null (unresolvable cluster). VerifyClaimMessage
        // still dispatches — the downstream handler falls back to flash-only
        // behaviour (S55 path preserved).
        $handler = $this->makeHandler();

        $signal = $this->makeSignal(10);
        $this->signalRepository->method('find')->willReturn($signal);
        $this->topicHashResolver->method('resolve')->willReturn(null);

        $graph = $this->makeGraph('topic-unresolvable');
        $this->aggregator->method('aggregate')->willReturn([$graph]);

        $handler(new AggregateSignalsMessage(topicHash: 'topic-unresolvable', signalIds: [10]));

        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(VerifyClaimMessage::class, $this->dispatched[0]);
        $this->assertNull($this->dispatched[0]->topicId);
    }

    public function testResolverSkippedWhenAggregatorReturnsNoGraphs(): void
    {
        // When the aggregator produces zero confirmed graphs there's nothing
        // to dispatch — we must NOT burn an LLM round-trip resolving a topic
        // for a cluster that will never produce a VerifyClaim.
        $handler = $this->makeHandler();

        $this->aggregator->method('aggregate')->willReturn([]);
        $this->topicHashResolver->expects($this->never())->method('resolve');
        $this->signalRepository->expects($this->never())->method('find');

        $handler(new AggregateSignalsMessage(topicHash: 'topic-empty', signalIds: [10]));

        $this->assertSame([], $this->dispatched);
    }

    private function makeHandler(): AggregateSignalsMessageHandler
    {
        return new AggregateSignalsMessageHandler(
            $this->aggregator,
            $this->messageBus,
            $this->appSettings,
            $this->signalRepository,
            $this->topicHashResolver,
            new NullLogger(),
        );
    }

    private function makeGraph(string $topicHash, string $claimHash = 'claim-1'): ClaimOriginGraph
    {
        return ClaimOriginGraph::fromArray([
            'topic_hash' => $topicHash,
            'claim_hash' => $claimHash,
            'nodes' => [],
            'edges' => [],
            'alignment_clusters' => ['wire_neutral'],
            'independent_chains' => 1,
            'tier_distribution' => ['1' => 1],
        ]);
    }

    private function makeSignal(int $id): SourceSignal&MockObject
    {
        $signal = $this->createMock(SourceSignal::class);
        $signal->method('getId')->willReturn($id);

        return $signal;
    }
}
