<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Editorial;

use App\Dto\Editorial\ClaimOriginGraph;
use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Editorial\SourceClaimHistory;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\ClaimOutcome;
use App\Enum\Editorial\VerdictType;
use App\Enum\EditorialAlignment;
use App\Message\Editorial\VerifyClaimMessage;
use App\MessageHandler\Editorial\VerifyClaimMessageHandler;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Editorial\Verification\VerificationGate;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class VerifyClaimMessageHandlerTest extends TestCase
{
    private SourceSignalRepository&MockObject $repository;
    private VerificationGate&MockObject $gate;
    private EntityManagerInterface&MockObject $em;
    private VerifyClaimMessageHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SourceSignalRepository::class);
        $this->gate = $this->createMock(VerificationGate::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->handler = new VerifyClaimMessageHandler(
            $this->repository,
            $this->gate,
            $this->em,
            new NullLogger(),
        );
    }

    public function testMissingSignalsSilentlyReturns(): void
    {
        $this->repository->method('find')->willReturn(null);
        $this->gate->expects($this->never())->method('rule');
        $this->em->expects($this->never())->method('flush');

        $message = new VerifyClaimMessage(
            topicHash: 'topic',
            graphArray: $this->minimalGraphArray(),
            signalIds: [42, 43],
        );

        $this->handler->__invoke($message);
    }

    public function testHappyPathPersistsSnapshotOnEachSignalAndCreatesClaimHistory(): void
    {
        $s1 = $this->makeSignal(1, tier: 1);
        $s2 = $this->makeSignal(2, tier: 2);

        $this->repository->method('find')
            ->willReturnMap([[1, null, $s1], [2, null, $s2]]);

        $verdict = new VerificationVerdict(
            type: VerdictType::FLASH_WITH_ATTRIBUTION,
            reasoning: 'Rule 1',
            llmOverride: false,
            llmSanitySkipped: false,
            escalationKeyword: null,
            confidence: 0.95,
        );

        $this->gate->expects($this->once())
            ->method('rule')
            ->with(
                $this->isInstanceOf(ClaimOriginGraph::class),
                $this->countOf(2),
            )
            ->willReturn($verdict);

        $persisted = [];
        $this->em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function ($entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        $this->em->expects($this->once())->method('flush');

        $this->handler->__invoke(new VerifyClaimMessage(
            topicHash: 'topic-abc',
            graphArray: $this->minimalGraphArray(),
            signalIds: [1, 2],
        ));

        // Snapshot persisted on BOTH signals.
        $s1Snapshot = $s1->getClaimGraphSnapshot();
        $s2Snapshot = $s2->getClaimGraphSnapshot();
        $this->assertIsArray($s1Snapshot);
        $this->assertIsArray($s2Snapshot);
        $this->assertSame(VerdictType::FLASH_WITH_ATTRIBUTION->value, $s1Snapshot['verdict']);
        $this->assertSame(VerdictType::FLASH_WITH_ATTRIBUTION->value, $s2Snapshot['verdict']);
        $this->assertSame('verification_gate_v1', $s1Snapshot['decided_by']);
        $this->assertArrayHasKey('decided_at', $s1Snapshot);
        $this->assertSame(0.95, $s1Snapshot['confidence']);

        // SourceClaimHistory row persisted with UNRESOLVED.
        $this->assertCount(1, $persisted);
        $history = $persisted[0];
        $this->assertInstanceOf(SourceClaimHistory::class, $history);
        $this->assertSame(ClaimOutcome::UNRESOLVED, $history->getOutcome());
        // Tier-1 signal is preferred as primary.
        $this->assertSame($s1->getVerifiedSource(), $history->getVerifiedSource());
    }

    public function testHistoryPrimaryFallsBackToEarliestWhenNoTier1(): void
    {
        $early = $this->makeSignal(1, tier: 2, capturedAt: new \DateTimeImmutable('2026-04-18 10:00:00'));
        $late = $this->makeSignal(2, tier: 2, capturedAt: new \DateTimeImmutable('2026-04-18 10:05:00'));

        $this->repository->method('find')
            ->willReturnMap([[1, null, $early], [2, null, $late]]);

        $this->gate->method('rule')->willReturn(new VerificationVerdict(
            type: VerdictType::REJECT,
            reasoning: 'Reject',
        ));

        $persisted = [];
        $this->em->method('persist')->willReturnCallback(
            function ($entity) use (&$persisted): void { $persisted[] = $entity; },
        );

        $this->handler->__invoke(new VerifyClaimMessage(
            topicHash: 'topic',
            graphArray: $this->minimalGraphArray(),
            signalIds: [1, 2],
        ));

        $history = $persisted[0];
        $this->assertInstanceOf(SourceClaimHistory::class, $history);
        // Earliest = $early
        $this->assertSame($early->getVerifiedSource(), $history->getVerifiedSource());
    }

    public function testHandlerSwallowsGateExceptions(): void
    {
        $s1 = $this->makeSignal(1, tier: 1);
        $this->repository->method('find')->willReturn($s1);

        $this->gate->method('rule')
            ->willThrowException(new \RuntimeException('gate exploded'));

        $this->em->expects($this->never())->method('flush');
        $this->em->expects($this->never())->method('persist');

        // Should NOT throw.
        $this->handler->__invoke(new VerifyClaimMessage('topic', $this->minimalGraphArray(), [1]));
    }

    /**
     * @return array<string, mixed>
     */
    private function minimalGraphArray(): array
    {
        return [
            'topic_hash' => 'topic',
            'claim_hash' => 'claim',
            'nodes' => [],
            'edges' => [],
            'alignment_clusters' => ['wire_neutral'],
            'independent_chains' => 1,
            'tier_distribution' => ['1' => 1],
        ];
    }

    private function makeSignal(int $id, int $tier, ?\DateTimeImmutable $capturedAt = null): SourceSignal
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

        $signal = new SourceSignal(
            verifiedSource: $vs,
            sourceUrl: 'https://example.com/' . $id,
            title: 'Titlu #' . $id,
            rawContentHash: str_repeat((string) ($id % 10), 64),
            capturedAt: $capturedAt,
        );

        $ref = new \ReflectionClass($signal);
        $idProp = $ref->getProperty('id');
        $idProp->setAccessible(true);
        $idProp->setValue($signal, $id);

        return $signal;
    }
}
