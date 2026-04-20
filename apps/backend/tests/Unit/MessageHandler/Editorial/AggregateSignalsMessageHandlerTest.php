<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Editorial;

use App\Dto\Editorial\ClaimOriginGraph;
use App\Message\Editorial\AggregateSignalsMessage;
use App\Message\Editorial\VerifyClaimMessage;
use App\MessageHandler\Editorial\AggregateSignalsMessageHandler;
use App\Repository\AppSettingRepository;
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

    /** @var list<object> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->aggregator = $this->createMock(SignalAggregator::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);

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
            $logger,
        );

        // Short-circuit must happen BEFORE any aggregator or dispatch call.
        $this->aggregator->expects($this->never())->method('aggregate');
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
        $handler = new AggregateSignalsMessageHandler(
            $this->aggregator,
            $this->messageBus,
            $this->appSettings,
            new NullLogger(),
        );

        $graph = ClaimOriginGraph::fromArray([
            'topic_hash' => 'topic-normal',
            'claim_hash' => 'claim-1',
            'nodes' => [],
            'edges' => [],
            'alignment_clusters' => ['wire_neutral'],
            'independent_chains' => 1,
            'tier_distribution' => ['1' => 1],
        ]);

        $this->aggregator->expects($this->once())
            ->method('aggregate')
            ->with('topic-normal', [10, 20])
            ->willReturn([$graph]);

        $handler(new AggregateSignalsMessage(topicHash: 'topic-normal', signalIds: [10, 20]));

        $this->assertCount(1, $this->dispatched);
        $this->assertInstanceOf(VerifyClaimMessage::class, $this->dispatched[0]);
    }
}
