<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Writer;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Dto\Agent\AgentResponse;
use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Enum\ArticleStatus;
use App\Enum\ArticleType;
use App\Enum\Editorial\VerdictType;
use App\Enum\EditorialAlignment;
use App\Enum\LlmModelTier;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Editorial\Llm\LlmPromptAssembler;
use App\Service\Editorial\Writer\DevelopingStoryWriter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see DevelopingStoryWriter} (Sprint 55 T55.4;
 * T57.P2c.4 AgentDispatcher migration; T57.P8 ADR-024 D3 downgrade-only
 * policy retirement).
 */
class DevelopingStoryWriterTest extends TestCase
{
    private AgentDispatcher&MockObject $dispatcher;
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;
    private DevelopingStoryWriter $writer;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->writer = new DevelopingStoryWriter(
            $this->dispatcher,
            $this->em,
            new LlmPromptAssembler(),
            $this->logger,
        );
    }

    public function testHappyPathIncrementsRevisionAndDispatchesReTranslation(): void
    {
        $existing = $this->existingStory(initialRev: 1);
        $primary = $this->mockSignal(200);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AgentRequest $req): bool {
                $this->assertSame('developing_story_writer', $req->agentId);
                $this->assertSame(LlmModelTier::HAIKU, $req->tier);
                $this->assertIsString($req->systemPrompt);

                return true;
            }))
            ->willReturn($this->buildAgentResponse(
                $this->happyPathResponse(
                    updatedContent: 'Corp actualizat cu detalii noi despre Chișinău. Diacritice corecte: ș, ț.',
                    changesSummary: 'S-au adăugat detalii despre ședința guvernului.',
                ),
            ));

        $this->em->expects($this->once())->method('flush');

        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'Cluster extins', confidence: 0.88);

        $result = $this->writer->write($existing, $primary, [], $verdict);

        $this->assertSame($existing, $result);
        $this->assertSame(2, $existing->getRevisionCount());
        $this->assertStringContainsString('ș', $existing->getContent() ?? '');
        $this->assertSame(0, preg_match('/[ŞşŢţ]/u', (string) $existing->getContent()));

        $history = $existing->getRevisionHistory() ?? [];
        $this->assertCount(1, $history);
        $this->assertSame(2, $history[0]['rev']);
        $this->assertSame('S-au adăugat detalii despre ședința guvernului.', $history[0]['diff']);
        $this->assertSame(200, $history[0]['source_signal_id']);
    }

    public function testThirdUpdateProducesRevisionThree(): void
    {
        $existing = $this->existingStory(initialRev: 2);

        $this->dispatcher->method('dispatch')->willReturn($this->buildAgentResponse($this->happyPathResponse()));

        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9);

        $this->writer->write($existing, $this->mockSignal(1), [], $verdict);

        $this->assertSame(3, $existing->getRevisionCount());
    }

    public function testRejectsArchivedArticle(): void
    {
        $existing = $this->existingStory(initialRev: 5);
        $existing->setStatus(ArticleStatus::ARCHIVED);

        $this->dispatcher->expects($this->never())->method('dispatch');
        $this->em->expects($this->never())->method('flush');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('developing_story_writer_skipped_archived', $this->isArray());

        $result = $this->writer->write(
            $existing,
            $this->mockSignal(1),
            [],
            new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9),
        );

        $this->assertNull($result);
        $this->assertSame(5, $existing->getRevisionCount());
    }

    public function testRejectsArticleWithWrongType(): void
    {
        $existing = $this->existingStory(initialRev: 3);
        $existing->setArticleType(ArticleType::FLASH);

        $this->dispatcher->expects($this->never())->method('dispatch');
        $this->em->expects($this->never())->method('flush');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('developing_story_writer_skipped_wrong_type', $this->isArray());

        $result = $this->writer->write(
            $existing,
            $this->mockSignal(1),
            [],
            new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9),
        );

        $this->assertNull($result);
    }

    /**
     * T57.P8 (ADR-024 D3) — downgrade-only policy retired. On LLM exhaust
     * the writer emits `editorial_review_queue` and rethrows; $existing stays
     * bit-exact (halt fires before extractString), no flush.
     */
    public function testLlmUnavailablePropagatesWithEditorialReviewLog(): void
    {
        $existing = $this->existingStory(initialRev: 1);
        $originalContent = $existing->getContent();

        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException(
                agentId: 'developing_story_writer',
                tier: LlmModelTier::HAIKU,
                attempts: 4,
            ),
        );

        $this->em->expects($this->never())->method('flush');

        $captured = null;
        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->willReturnCallback(function (string $channel, array $payload) use (&$captured): void {
                if ($channel === 'editorial_review_queue') {
                    $captured = $payload;
                }
            });

        $this->expectException(LlmUnavailableException::class);

        try {
            $this->writer->write(
                $existing,
                $this->mockSignal(500),
                [],
                new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9),
            );
        } finally {
            $this->assertIsArray($captured, 'editorial_review_queue log line must be emitted');
            $this->assertSame('developing_story_writer', $captured['agent_id']);
            $this->assertSame('article', $captured['entity_type']);
            $this->assertSame(42, $captured['entity_id']);
            $this->assertSame(['signal_id' => 500], $captured['entity_refs']);
            $this->assertNull($captured['invocation_id']);
            $this->assertSame('haiku', $captured['tier_attempted']);
            $this->assertSame('haiku_exhausted', $captured['reason']);

            // Existing Article stays bit-exact.
            $this->assertSame($originalContent, $existing->getContent());
            $this->assertSame(1, $existing->getRevisionCount());
        }
    }

    public function testUpdatedTitleOverwritesWhenProvided(): void
    {
        $existing = $this->existingStory(initialRev: 1);
        $existing->setTitle('Titlu vechi');

        $this->dispatcher->method('dispatch')->willReturn($this->buildAgentResponse(
            $this->happyPathResponse(updatedTitle: 'Titlu nou actualizat'),
        ));

        $this->writer->write(
            $existing,
            $this->mockSignal(1),
            [],
            new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9),
        );

        $this->assertSame('Titlu nou actualizat', $existing->getTitle());
    }

    public function testNullUpdatedTitleKeepsExistingTitle(): void
    {
        $existing = $this->existingStory(initialRev: 1);
        $existing->setTitle('Titlu neschimbat');

        $this->dispatcher->method('dispatch')->willReturn($this->buildAgentResponse(
            $this->happyPathResponse(updatedTitle: null),
        ));

        $this->writer->write(
            $existing,
            $this->mockSignal(1),
            [],
            new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9),
        );

        $this->assertSame('Titlu neschimbat', $existing->getTitle());
    }

    public function testRevisionHistoryCapEnforcedAt100Entries(): void
    {
        $existing = $this->existingStory(initialRev: 99);
        for ($i = 1; $i <= 99; $i++) {
            $existing->appendRevision(['rev' => $i, 'diff' => "seed-{$i}"]);
        }

        $this->dispatcher->method('dispatch')->willReturn($this->buildAgentResponse(
            $this->happyPathResponse(changesSummary: 'Update 100'),
        ));

        $this->writer->write(
            $existing,
            $this->mockSignal(1),
            [],
            new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9),
        );

        $history = $existing->getRevisionHistory() ?? [];
        $this->assertCount(100, $history);
        $this->assertSame(100, $existing->getRevisionCount());
        $this->assertSame('Update 100', $history[99]['diff']);

        // Now push one more — oldest should be dropped.
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->dispatcher->method('dispatch')->willReturn($this->buildAgentResponse(
            $this->happyPathResponse(changesSummary: 'Update 101'),
        ));
        $writer = new DevelopingStoryWriter(
            $this->dispatcher,
            $this->em,
            new LlmPromptAssembler(),
            $this->logger,
        );

        $writer->write(
            $existing,
            $this->mockSignal(2),
            [],
            new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9),
        );

        $history = $existing->getRevisionHistory() ?? [];
        $this->assertCount(100, $history, 'history capped at 100');
        $this->assertSame(101, $existing->getRevisionCount(), 'counter continues past cap');
        $this->assertSame('seed-2', $history[0]['diff'], 'oldest entry dropped (seed-1 gone, seed-2 now at index 0)');
        $this->assertSame('Update 101', $history[99]['diff'], 'newest entry at tail');
    }

    public function testSupportingSignalsAccumulateSourceCount(): void
    {
        $existing = $this->existingStory(initialRev: 1);
        $existing->setAiSourceCount(3);

        $this->dispatcher->method('dispatch')->willReturn($this->buildAgentResponse($this->happyPathResponse()));

        $primary = $this->mockSignal(10);
        $supporting = [$this->mockSignal(11), $this->mockSignal(12)];

        $this->writer->write($existing, $primary, $supporting, new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9));

        // 3 (previous) + 1 (primary) + 2 (supporting) = 6
        $this->assertSame(6, $existing->getAiSourceCount());
    }

    public function testContentHashUpdatesOnNewContent(): void
    {
        $existing = $this->existingStory(initialRev: 1);
        $existing->setContentHash(str_repeat('0', 64));

        $newContent = 'Corp complet diferit care produce alt hash.';
        $this->dispatcher->method('dispatch')->willReturn($this->buildAgentResponse(
            $this->happyPathResponse(updatedContent: $newContent),
        ));

        $this->writer->write($existing, $this->mockSignal(1), [], new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9));

        $this->assertSame(hash('sha256', $newContent), $existing->getContentHash());
    }

    /**
     * T57.P2c.4 acceptance (d'): CRITICAL — editorial.emergency_halt must NOT
     * flush any $existing mutations mid-invocation.
     */
    public function testEmergencyHaltExceptionPropagatesWithoutFlushing(): void
    {
        $existing = $this->existingStory(initialRev: 5);
        $existing->setTitle('Titlu protected');
        $originalContent = $existing->getContent();

        $this->dispatcher->method('dispatch')->willThrowException(
            new EmergencyHaltException('developing_story_writer'),
        );

        $this->em->expects($this->never())->method('flush');

        $this->expectException(EmergencyHaltException::class);

        try {
            $this->writer->write(
                $existing,
                $this->mockSignal(300),
                [],
                new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9),
            );
        } finally {
            $this->assertSame('Titlu protected', $existing->getTitle());
            $this->assertSame($originalContent, $existing->getContent());
            $this->assertSame(5, $existing->getRevisionCount());
        }
    }

    /**
     * T57.P2c.4 acceptance (c + AgentRequest shape): dispatcher receives
     * an AgentRequest carrying agentId=developing_story_writer, hardcoded
     * HAIKU tier (Pattern-B constant), the system prompt, and no tierVariant.
     */
    public function testDispatchReceivesAgentRequestWithHardcodedHaikuTier(): void
    {
        $existing = $this->existingStory(initialRev: 1);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AgentRequest $req): bool {
                $this->assertSame('developing_story_writer', $req->agentId);
                $this->assertSame(LlmModelTier::HAIKU, $req->tier);
                $this->assertNotNull($req->systemPrompt);
                $this->assertStringContainsString('actualizează o știre în curs', $req->systemPrompt);
                $this->assertCount(1, $req->messages);
                $this->assertSame('user', $req->messages[0]['role']);
                $this->assertNull($req->tierVariant, 'DevelopingStoryWriter has no variant');

                return true;
            }))
            ->willReturn($this->buildAgentResponse($this->happyPathResponse()));

        $this->writer->write(
            $existing,
            $this->mockSignal(1),
            [],
            new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9),
        );
    }

    private function buildAgentResponse(string $content): AgentResponse
    {
        return new AgentResponse(
            content: $content,
            agentId: 'developing_story_writer',
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
            metrics: null,
        );
    }

    private function existingStory(int $initialRev): Article
    {
        $article = new Article();
        $ref = new \ReflectionProperty(Article::class, 'id');
        $ref->setValue($article, 42);

        $article->setTitle('Știre în curs — revision ' . $initialRev);
        $article->setLead('Lead existent cu diacritice: ș, ț.');
        $article->setContent('Corp existent cu mai multe fraze. Menționează evenimentul principal și context anterior.');
        $article->setStatus(ArticleStatus::PUBLISHED);
        $article->setArticleType(ArticleType::DEVELOPING_STORY);
        $article->setRevisionCount($initialRev);
        $article->setAiGenerated(true);

        return $article;
    }

    private function mockSignal(int $id): SourceSignal
    {
        $verifiedSource = $this->createMock(VerifiedSource::class);
        $verifiedSource->method('getEditorialAlignment')->willReturn(EditorialAlignment::WIRE_NEUTRAL);
        $verifiedSource->method('getName')->willReturn('Mock Source');

        $signal = $this->createMock(SourceSignal::class);
        $signal->method('getId')->willReturn($id);
        $signal->method('getTitle')->willReturn('Semnal nou #' . $id);
        $signal->method('getRawSummary')->willReturn('Rezumat semnal');
        $signal->method('getVerifiedSource')->willReturn($verifiedSource);

        return $signal;
    }

    private function happyPathResponse(
        ?string $updatedTitle = null,
        ?string $updatedLead = null,
        string $updatedContent = 'Corp actualizat cu diacritice ș și ț.',
        string $changesSummary = 'S-au adăugat detalii noi.',
    ): string {
        return json_encode([
            'updated_title' => $updatedTitle,
            'updated_lead' => $updatedLead,
            'updated_content' => $updatedContent,
            'changes_summary' => $changesSummary,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
