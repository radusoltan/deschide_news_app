<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Editorial;

use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Entity\Topic;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\Editorial\VerdictType;
use App\Enum\EditorialAlignment;
use App\Message\Editorial\VerifyClaimMessage;
use App\Message\Editorial\WriteDevelopingStoryMessage;
use App\Message\Editorial\WriteFlashMessage;
use App\MessageHandler\Editorial\VerifyClaimMessageHandler;
use App\Repository\AppSettingRepository;
use App\Repository\ArticleRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Repository\TopicRepository;
use App\Service\Editorial\Escalation\EscalationClassifier;
use App\Service\Editorial\Escalation\EscalationLogWriter;
use App\Service\Editorial\Verification\VerificationGate;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Sprint 55 T55.9 — keystone branch tests for {@see VerifyClaimMessageHandler}.
 *
 * Covers the new verdict-based dispatch layer that sits on top of the S54
 * snapshot+history audit trail:
 *   - pipeline.enabled=false → snapshot persists, NO downstream dispatch
 *   - REJECT → noop (no writer dispatch, no escalation log)
 *   - ESCALATE_HUMAN → EscalationClassifier + EscalationLogWriter invoked
 *   - FLASH_* with no developing story → WriteFlashMessage dispatched
 *   - FLASH_* with developing story present → WriteDevelopingStoryMessage dispatched
 *   - Idempotency: existing Article for primary signal skips writer dispatch
 */
class VerifyClaimMessageHandlerT559Test extends TestCase
{
    private SourceSignalRepository&MockObject $repository;
    private VerificationGate&MockObject $gate;
    private EntityManagerInterface&MockObject $em;
    private TopicRepository&MockObject $topicRepository;
    private ArticleRepository&MockObject $articleRepository;
    private EscalationClassifier&MockObject $escalationClassifier;
    private EscalationLogWriter&MockObject $escalationLogWriter;
    private MessageBusInterface&MockObject $messageBus;
    private AppSettingRepository&MockObject $appSettings;
    private VerifyClaimMessageHandler $handler;

    /** @var list<object> */
    private array $dispatched = [];

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SourceSignalRepository::class);
        $this->gate = $this->createMock(VerificationGate::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->topicRepository = $this->createMock(TopicRepository::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->escalationClassifier = $this->createMock(EscalationClassifier::class);
        $this->escalationLogWriter = $this->createMock(EscalationLogWriter::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);

        $this->dispatched = [];
        $this->messageBus->method('dispatch')
            ->willReturnCallback(function (object $m): Envelope {
                $this->dispatched[] = $m;

                return new Envelope($m);
            });

        // Default: pipeline ON, emergency_halt OFF — override per-test where needed.
        $this->appSettings->method('getBool')
            ->willReturnMap([
                ['editorial.emergency_halt', false, false],
                ['editorial.pipeline.enabled', false, true],
            ]);

        $this->handler = new VerifyClaimMessageHandler(
            $this->repository,
            $this->gate,
            $this->em,
            $this->topicRepository,
            $this->articleRepository,
            $this->escalationClassifier,
            $this->escalationLogWriter,
            $this->messageBus,
            $this->appSettings,
            new NullLogger(),
        );
    }

    public function testPipelineDisabledSkipsDispatchButKeepsSnapshot(): void
    {
        $settings = $this->createMock(AppSettingRepository::class);
        $settings->method('getBool')->willReturn(false);

        $handler = new VerifyClaimMessageHandler(
            $this->repository,
            $this->gate,
            $this->em,
            $this->topicRepository,
            $this->articleRepository,
            $this->escalationClassifier,
            $this->escalationLogWriter,
            $this->messageBus,
            $settings,
            new NullLogger(),
        );

        $signal = $this->mockSignal(1);
        $this->repository->method('find')->willReturn($signal);
        $this->gate->method('rule')->willReturn(new VerificationVerdict(
            type: VerdictType::FULL_FLASH,
            reasoning: 'ok',
            confidence: 0.9,
        ));

        // Audit trail still runs.
        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        // But no downstream dispatch.
        $this->escalationLogWriter->expects($this->never())->method('write');

        $handler(new VerifyClaimMessage('topic', $this->minGraph(), [1]));

        // No writer messages dispatched.
        $this->assertCount(0, array_filter($this->dispatched, fn ($m): bool =>
            $m instanceof WriteFlashMessage || $m instanceof WriteDevelopingStoryMessage));
    }

    public function testRejectVerdictNoops(): void
    {
        $signal = $this->mockSignal(1);
        $this->repository->method('find')->willReturn($signal);
        $this->gate->method('rule')->willReturn(new VerificationVerdict(
            type: VerdictType::REJECT,
            reasoning: 'low trust',
            confidence: 0.4,
        ));

        $this->escalationLogWriter->expects($this->never())->method('write');

        $this->handler->__invoke(new VerifyClaimMessage('topic', $this->minGraph(), [1]));

        // No writer dispatch.
        $writerDispatches = array_filter($this->dispatched, fn ($m): bool =>
            $m instanceof WriteFlashMessage || $m instanceof WriteDevelopingStoryMessage);
        $this->assertCount(0, $writerDispatches);
    }

    public function testEscalateHumanVerdictInvokesClassifierAndLogWriter(): void
    {
        $signal = $this->mockSignal(42, 'Claim escaladat');
        $this->repository->method('find')->willReturn($signal);

        $this->gate->method('rule')->willReturn(new VerificationVerdict(
            type: VerdictType::ESCALATE_HUMAN,
            reasoning: 'Rule 0 nuclear',
            confidence: 1.0,
            escalationKeyword: 'war_adjacent:nuclear',
        ));

        $this->escalationClassifier->expects($this->once())
            ->method('classify')
            ->willReturn(EscalationCategory::CATEGORY_1_NUCLEAR_WAR);

        $this->escalationLogWriter->expects($this->once())
            ->method('write')
            ->with(
                EscalationCategory::CATEGORY_1_NUCLEAR_WAR,
                $this->callback(function (array $snapshot): bool {
                    return $snapshot['primary_signal_id'] === 42
                        && $snapshot['verdict_type'] === 'escalate_human';
                }),
                $this->isArray(),
            );

        $this->handler->__invoke(new VerifyClaimMessage('topic', $this->minGraph(), [42]));
    }

    public function testEscalateHumanFallsBackToFamilyBWhenClassifierNull(): void
    {
        $signal = $this->mockSignal(7);
        $this->repository->method('find')->willReturn($signal);

        $this->gate->method('rule')->willReturn(new VerificationVerdict(
            type: VerdictType::ESCALATE_HUMAN,
            reasoning: 'escalate',
            confidence: 0.85,
        ));

        // Classifier returns null (low confidence / LLM unavailable).
        $this->escalationClassifier->method('classify')->willReturn(null);

        $this->escalationLogWriter->expects($this->once())
            ->method('write')
            ->with(
                EscalationCategory::FAMILY_B_EU_NATO_RUSSIA,
                $this->isArray(),
                $this->isArray(),
            );

        $this->handler->__invoke(new VerifyClaimMessage('topic', $this->minGraph(), [7]));
    }

    public function testFlashVerdictWithoutDevelopingStoryDispatchesFlash(): void
    {
        $signal = $this->mockSignal(100);
        $this->repository->method('find')->willReturn($signal);
        $this->articleRepository->method('findOneBy')->willReturn(null); // no existing Article
        $this->articleRepository->method('findDevelopingStoryForTopic')->willReturn(null);

        $topic = $this->mockTopic(55);
        $this->topicRepository->method('find')->willReturn($topic);

        $this->gate->method('rule')->willReturn(new VerificationVerdict(
            type: VerdictType::FULL_FLASH,
            reasoning: 'ok',
            confidence: 0.92,
        ));

        $this->handler->__invoke(new VerifyClaimMessage(
            topicHash: 'topic',
            graphArray: $this->minGraph(),
            signalIds: [100],
            topicId: 55,
        ));

        $flashMsgs = array_values(array_filter(
            $this->dispatched,
            fn ($m): bool => $m instanceof WriteFlashMessage,
        ));
        $this->assertCount(1, $flashMsgs);
        $this->assertSame(100, $flashMsgs[0]->primarySignalId);
        $this->assertSame(55, $flashMsgs[0]->topicId);
        $this->assertSame('full_flash', $flashMsgs[0]->verdictType);
    }

    public function testFlashVerdictWithDevelopingStoryDispatchesDeveloping(): void
    {
        $signal = $this->mockSignal(200);
        $this->repository->method('find')->willReturn($signal);
        $this->articleRepository->method('findOneBy')->willReturn(null);

        $topic = $this->mockTopic(77);
        $this->topicRepository->method('find')->willReturn($topic);

        // A developing story exists for this topic → dispatch WriteDevelopingStoryMessage
        $existingArticle = $this->createMock(Article::class);
        $existingArticle->method('getId')->willReturn(999);
        $this->articleRepository->method('findDevelopingStoryForTopic')
            ->with($topic, $this->isInstanceOf(\DateTimeImmutable::class))
            ->willReturn($existingArticle);

        $this->gate->method('rule')->willReturn(new VerificationVerdict(
            type: VerdictType::FLASH_WITH_ATTRIBUTION,
            reasoning: 'single-chain',
            confidence: 0.7,
        ));

        $this->handler->__invoke(new VerifyClaimMessage(
            topicHash: 'topic',
            graphArray: $this->minGraph(),
            signalIds: [200],
            topicId: 77,
        ));

        $devMsgs = array_values(array_filter(
            $this->dispatched,
            fn ($m): bool => $m instanceof WriteDevelopingStoryMessage,
        ));
        $this->assertCount(1, $devMsgs);
        $this->assertSame(999, $devMsgs[0]->articleId);
        $this->assertSame(200, $devMsgs[0]->primarySignalId);

        // No WriteFlash dispatched — developing path is exclusive.
        $flashMsgs = array_values(array_filter(
            $this->dispatched,
            fn ($m): bool => $m instanceof WriteFlashMessage,
        ));
        $this->assertCount(0, $flashMsgs);
    }

    public function testIdempotencySkipsWriterWhenArticleAlreadyExists(): void
    {
        $signal = $this->mockSignal(300);
        $this->repository->method('find')->willReturn($signal);

        $existing = $this->createMock(Article::class);
        $existing->method('getId')->willReturn(1234);
        $this->articleRepository->method('findOneBy')
            ->with(['originalSourceSignal' => $signal])
            ->willReturn($existing);

        $this->gate->method('rule')->willReturn(new VerificationVerdict(
            type: VerdictType::FULL_FLASH,
            reasoning: 'ok',
            confidence: 0.9,
        ));

        $this->handler->__invoke(new VerifyClaimMessage('topic', $this->minGraph(), [300]));

        // No writer messages dispatched — idempotency short-circuit.
        $writerMsgs = array_filter($this->dispatched, fn ($m): bool =>
            $m instanceof WriteFlashMessage || $m instanceof WriteDevelopingStoryMessage);
        $this->assertCount(0, $writerMsgs);
    }

    public function testNullTopicStillDispatchesFlash(): void
    {
        // Writer path tolerates null topic — T55.9 behavior.
        $signal = $this->mockSignal(400);
        $this->repository->method('find')->willReturn($signal);
        $this->articleRepository->method('findOneBy')->willReturn(null);

        $this->gate->method('rule')->willReturn(new VerificationVerdict(
            type: VerdictType::FULL_FLASH,
            reasoning: 'ok',
            confidence: 0.9,
        ));

        $this->handler->__invoke(new VerifyClaimMessage(
            topicHash: 'topic',
            graphArray: $this->minGraph(),
            signalIds: [400],
            topicId: null,
        ));

        $flashMsgs = array_values(array_filter(
            $this->dispatched,
            fn ($m): bool => $m instanceof WriteFlashMessage,
        ));
        $this->assertCount(1, $flashMsgs);
        $this->assertNull($flashMsgs[0]->topicId);
    }

    // ========== helpers ==========

    /**
     * @return array<string, mixed>
     */
    private function minGraph(): array
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

    private function mockSignal(int $id, string $title = 'Test claim'): SourceSignal
    {
        $source = new Source();
        $source->setName('Source-' . $id);

        $verified = new VerifiedSource(
            slug: 'src-' . $id,
            tier: 1,
            editorialAlignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.8',
            source: $source,
        );

        $signal = new SourceSignal(
            verifiedSource: $verified,
            sourceUrl: 'https://example.com/' . $id,
            title: $title,
            rawContentHash: str_repeat((string) ($id % 10), 64),
        );

        $ref = new \ReflectionProperty(SourceSignal::class, 'id');
        $ref->setValue($signal, $id);

        return $signal;
    }

    private function mockTopic(int $id): Topic
    {
        $topic = new Topic();
        $topic->setTitle('Topic ' . $id);
        $topic->setSlug('topic-' . $id);

        $ref = new \ReflectionProperty(Topic::class, 'id');
        $ref->setValue($topic, $id);

        return $topic;
    }
}
