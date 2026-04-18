<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Writer;

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
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\Provider\GeminiCliService;
use App\Message\TranslateArticleMessage;
use App\Repository\ImportantArticlesListRepository;
use App\Service\Editorial\Writer\DevelopingStoryWriter;
use App\Service\TranslationPriorityDispatcher;
use App\Service\TranslationPriorityResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Unit test for {@see DevelopingStoryWriter} (Sprint 55 T55.4).
 *
 * Verifies in-place revision mechanics, archived-article guard, wrong-type
 * guard, diacritics preservation, and targeted re-translation dispatch.
 */
class DevelopingStoryWriterTest extends TestCase
{
    private LlmRetryExecutor&MockObject $llmRetryExecutor;
    private GeminiCliService&MockObject $geminiCliService;
    private MessageBusInterface&MockObject $messageBus;
    private TranslationPriorityDispatcher $translationDispatcher;
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;
    private DevelopingStoryWriter $writer;

    /** @var list<TranslateArticleMessage> */
    private array $dispatchedTranslations = [];

    protected function setUp(): void
    {
        $this->llmRetryExecutor = $this->createMock(LlmRetryExecutor::class);
        $this->geminiCliService = $this->createMock(GeminiCliService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->dispatchedTranslations = [];
        $this->messageBus->method('dispatch')
            ->willReturnCallback(function (object $envelopeOrMessage): Envelope {
                $envelope = $envelopeOrMessage instanceof Envelope
                    ? $envelopeOrMessage
                    : new Envelope($envelopeOrMessage);
                $inner = $envelope->getMessage();
                if ($inner instanceof TranslateArticleMessage) {
                    $this->dispatchedTranslations[] = $inner;
                }

                return $envelope;
            });

        // TranslationPriorityDispatcher is final readonly — use a real instance
        // with a mocked MessageBus so we can observe the TranslateArticleMessage
        // envelope the writer emits.
        $importantRepo = $this->createMock(ImportantArticlesListRepository::class);
        $this->translationDispatcher = new TranslationPriorityDispatcher(
            $this->messageBus,
            new TranslationPriorityResolver($importantRepo),
            $this->createMock(LoggerInterface::class),
        );

        $this->writer = new DevelopingStoryWriter(
            $this->llmRetryExecutor,
            $this->geminiCliService,
            $this->translationDispatcher,
            $this->em,
            $this->logger,
        );
    }

    public function testHappyPathIncrementsRevisionAndDispatchesReTranslation(): void
    {
        $existing = $this->existingStory(initialRev: 1);
        $primary = $this->mockSignal(200);

        $this->llmRetryExecutor->expects($this->once())
            ->method('executeWithRetry')
            ->with(
                'developing_story_writer',
                $this->isArray(),
                LlmModelTier::HAIKU,
                $this->isString(),
            )
            ->willReturn([
                'content' => $this->happyPathResponse(
                    updatedContent: 'Corp actualizat cu detalii noi despre Chișinău. Diacritice corecte: ș, ț.',
                    changesSummary: 'S-au adăugat detalii despre ședința guvernului.',
                ),
                'agent_id' => 'developing_story_writer',
                'tier' => 'haiku',
                'model' => 'claude-haiku-4-5-20251001',
                'attempts' => 1,
                'fallback_detected' => false,
                'metrics' => null,
            ]);

        $this->geminiCliService->expects($this->never())->method('execute');
        $this->em->expects($this->once())->method('flush');

        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'Cluster extins', confidence: 0.88);

        $result = $this->writer->write($existing, $primary, [], $verdict);

        // TranslationPriorityDispatcher emitted exactly one TranslateArticleMessage
        // with forceRetranslate=true on both ru + en locales.
        $this->assertCount(1, $this->dispatchedTranslations);
        $this->assertTrue($this->dispatchedTranslations[0]->forceRetranslate);
        $this->assertEqualsCanonicalizing(['ru', 'en'], $this->dispatchedTranslations[0]->locales);

        $this->assertSame($existing, $result);
        $this->assertSame(2, $existing->getRevisionCount());
        $this->assertStringContainsString('ș', $existing->getContent() ?? '');
        $this->assertSame(0, preg_match('/[ŞşŢţ]/u', (string) $existing->getContent()));

        $history = $existing->getRevisionHistory() ?? [];
        $this->assertCount(1, $history); // testFixture starts with empty revision_history
        $this->assertSame(2, $history[0]['rev']);
        $this->assertSame('S-au adăugat detalii despre ședința guvernului.', $history[0]['diff']);
        $this->assertSame(200, $history[0]['source_signal_id']);
    }

    public function testThirdUpdateProducesRevisionThree(): void
    {
        $existing = $this->existingStory(initialRev: 2);

        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $this->happyPathResponse(),
            'agent_id' => 'developing_story_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9);

        $this->writer->write($existing, $this->mockSignal(1), [], $verdict);

        $this->assertSame(3, $existing->getRevisionCount());
    }

    public function testRejectsArchivedArticle(): void
    {
        $existing = $this->existingStory(initialRev: 5);
        $existing->setStatus(ArticleStatus::ARCHIVED);

        $this->llmRetryExecutor->expects($this->never())->method('executeWithRetry');
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
        $this->assertSame(5, $existing->getRevisionCount()); // unchanged
    }

    public function testRejectsArticleWithWrongType(): void
    {
        $existing = $this->existingStory(initialRev: 3);
        $existing->setArticleType(ArticleType::FLASH); // not DEVELOPING_STORY

        $this->llmRetryExecutor->expects($this->never())->method('executeWithRetry');
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

    public function testGeminiFallbackWhenHaikuExhausted(): void
    {
        $existing = $this->existingStory(initialRev: 1);

        $this->llmRetryExecutor->method('executeWithRetry')->willThrowException(
            new LlmUnavailableException(
                agentId: 'developing_story_writer',
                tier: LlmModelTier::HAIKU,
                fallbackTier: LlmModelTier::GEMINI_FLASH,
                attempts: 4,
            ),
        );

        $this->geminiCliService->expects($this->once())
            ->method('execute')
            ->willReturn($this->happyPathResponse());

        $this->em->expects($this->once())->method('flush');

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('developing_story_writer_haiku_unavailable_trying_gemini', $this->isArray());

        $result = $this->writer->write(
            $existing,
            $this->mockSignal(1),
            [],
            new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9),
        );

        $this->assertSame($existing, $result);
        $this->assertCount(1, $this->dispatchedTranslations);
    }

    public function testUpdatedTitleOverwritesWhenProvided(): void
    {
        $existing = $this->existingStory(initialRev: 1);
        $existing->setTitle('Titlu vechi');

        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $this->happyPathResponse(updatedTitle: 'Titlu nou actualizat'),
            'agent_id' => 'developing_story_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

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

        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $this->happyPathResponse(updatedTitle: null),
            'agent_id' => 'developing_story_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

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
        // Seed article already at cap boundary: 99 existing entries, count=99.
        $existing = $this->existingStory(initialRev: 99);
        for ($i = 1; $i <= 99; $i++) {
            $existing->appendRevision(['rev' => $i, 'diff' => "seed-{$i}"]);
        }

        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $this->happyPathResponse(changesSummary: 'Update 100'),
            'agent_id' => 'developing_story_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

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
        $this->llmRetryExecutor = $this->createMock(LlmRetryExecutor::class);
        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $this->happyPathResponse(changesSummary: 'Update 101'),
            'agent_id' => 'developing_story_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);
        $writer = new DevelopingStoryWriter(
            $this->llmRetryExecutor,
            $this->geminiCliService,
            $this->translationDispatcher,
            $this->em,
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
        $existing->setAiSourceCount(3); // initial flash had 3 sources

        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $this->happyPathResponse(),
            'agent_id' => 'developing_story_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

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
        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $this->happyPathResponse(updatedContent: $newContent),
            'agent_id' => 'developing_story_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $this->writer->write($existing, $this->mockSignal(1), [], new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9));

        $this->assertSame(hash('sha256', $newContent), $existing->getContentHash());
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
