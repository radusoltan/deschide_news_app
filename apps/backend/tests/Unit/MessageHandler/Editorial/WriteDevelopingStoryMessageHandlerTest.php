<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Editorial;

use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Enum\ArticleStatus;
use App\Enum\ArticleType;
use App\Enum\Editorial\EscalationCategory;
use App\Message\Editorial\WriteDevelopingStoryMessage;
use App\MessageHandler\Editorial\WriteDevelopingStoryMessageHandler;
use App\Repository\AppSettingRepository;
use App\Repository\ArticleRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Editorial\Escalation\EscalationLogWriter;
use App\Service\Editorial\Guard\GuardEscalationCategoryMapper;
use App\Service\Editorial\Guard\GuardPipelineInterface;
use App\Service\Editorial\Guard\GuardVerdict;
use App\Service\Editorial\Guard\LegalGuard;
use App\Message\TranslateArticleMessage;
use App\Repository\ImportantArticlesListRepository;
use App\Service\Editorial\Writer\DevelopingStoryWriter;
use App\Service\Editorial\WriterThrottle;
use App\Service\TranslationPriorityDispatcher;
use App\Service\TranslationPriorityResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * Unit test for {@see WriteDevelopingStoryMessageHandler} (Sprint 55 T55.4 + T55.9).
 */
class WriteDevelopingStoryMessageHandlerTest extends TestCase
{
    private DevelopingStoryWriter&MockObject $writer;
    private GuardPipelineInterface&MockObject $guardPipeline;
    private MessageBusInterface&MockObject $messageBus;
    private TranslationPriorityDispatcher $translationDispatcher;
    private EscalationLogWriter&MockObject $escalationLogWriter;
    private ArticleRepository&MockObject $articleRepository;
    private SourceSignalRepository&MockObject $signalRepository;
    private EntityManagerInterface&MockObject $em;
    private AppSettingRepository&MockObject $appSettings;
    private WriterThrottle&MockObject $writerThrottle;
    private LoggerInterface&MockObject $logger;
    private WriteDevelopingStoryMessageHandler $handler;

    /** @var list<TranslateArticleMessage> */
    private array $dispatchedTranslations = [];

    protected function setUp(): void
    {
        $this->writer = $this->createMock(DevelopingStoryWriter::class);
        $this->guardPipeline = $this->createMock(GuardPipelineInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->escalationLogWriter = $this->createMock(EscalationLogWriter::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->signalRepository = $this->createMock(SourceSignalRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);
        // Default: emergency_halt=false so the existing happy-path tests exercise
        // real delegation. The emergency_halt test overrides with a fresh mock.
        $this->appSettings->method('getBool')
            ->with('editorial.emergency_halt', false)
            ->willReturn(false);
        $this->writerThrottle = $this->createMock(WriterThrottle::class);
        $this->writerThrottle->method('consume')
            ->willReturn(new RateLimit(9, new \DateTimeImmutable('+1 hour'), true, 10));
        $this->logger = $this->createMock(LoggerInterface::class);

        // TranslationPriorityDispatcher is final readonly — use a real instance
        // with a mocked MessageBus so the handler's dispatch() call is
        // observable in tests.
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

        $importantRepo = $this->createMock(ImportantArticlesListRepository::class);
        $this->translationDispatcher = new TranslationPriorityDispatcher(
            $this->messageBus,
            new TranslationPriorityResolver($importantRepo),
            new NullLogger(),
        );

        $this->handler = new WriteDevelopingStoryMessageHandler(
            $this->writer,
            $this->guardPipeline,
            $this->translationDispatcher,
            $this->escalationLogWriter,
            new GuardEscalationCategoryMapper(),
            $this->articleRepository,
            $this->signalRepository,
            $this->em,
            $this->appSettings,
            $this->writerThrottle,
            $this->messageBus,
            $this->logger,
        );
    }

    public function testGuardPassDispatchesReTranslation(): void
    {
        $article = $this->buildDevelopingArticle();
        $primary = $this->mockSignal(100);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturn($primary);

        $this->writer->method('write')->willReturn($article);

        $this->guardPipeline->method('check')->willReturn(new GuardVerdict(passed: true));

        $this->escalationLogWriter->expects($this->never())->method('write');

        $message = new WriteDevelopingStoryMessage(42, 100, [], 'full_flash');
        $result = ($this->handler)($message);

        $this->assertSame($article, $result);
        // Handler dispatched a TranslateArticleMessage with forceRetranslate=true.
        $this->assertCount(1, $this->dispatchedTranslations);
        $this->assertTrue($this->dispatchedTranslations[0]->forceRetranslate);
        $this->assertEqualsCanonicalizing(['ru', 'en'], $this->dispatchedTranslations[0]->locales);
    }

    public function testGuardEscalationArchivesAndLogs(): void
    {
        $article = $this->buildDevelopingArticle();
        $primary = $this->mockSignal(200);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->writer->method('write')->willReturn($article);

        $this->guardPipeline->method('check')->willReturn(new GuardVerdict(
            passed: false,
            failures: ['[legal] risc înalt'],
            escalationCode: LegalGuard::ESCALATION_CODE_CATEGORY_6,
        ));

        $this->em->expects($this->once())->method('flush');

        $this->escalationLogWriter->expects($this->once())
            ->method('write')
            ->with(
                EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION,
                $this->callback(function (array $snapshot): bool {
                    // Developing-story snapshot carries revision_count.
                    return array_key_exists('revision_count', $snapshot);
                }),
                $this->isArray(),
            );

        // TranslationPriorityDispatcher is a real instance; verify nothing was dispatched.

        $message = new WriteDevelopingStoryMessage(42, 200, [], 'full_flash');
        ($this->handler)($message);

        $this->assertSame(ArticleStatus::ARCHIVED, $article->getStatus());
        $this->assertCount(0, $this->dispatchedTranslations, 'Archived Article must not dispatch translations');
    }

    public function testGuardFlagAnnotatesInternalSummaryNoDispatch(): void
    {
        $article = $this->buildDevelopingArticle();
        $primary = $this->mockSignal(300);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->writer->method('write')->willReturn($article);

        $this->guardPipeline->method('check')->willReturn(new GuardVerdict(
            passed: false,
            failures: ['[style:tone:medium] ton prea subiectiv'],
            escalationCode: null,
        ));

        // TranslationPriorityDispatcher is a real instance; verify nothing was dispatched.
        $this->escalationLogWriter->expects($this->never())->method('write');

        $message = new WriteDevelopingStoryMessage(42, 300, [], 'full_flash');
        ($this->handler)($message);

        $this->assertSame(ArticleStatus::PUBLISHED, $article->getStatus());
        $this->assertStringContainsString('[guard_flag:', (string) $article->getInternalSummary());
        $this->assertCount(0, $this->dispatchedTranslations, 'Flagged Article must not dispatch re-translation');
    }

    public function testWriterRejectsSkipsGuardAndDispatch(): void
    {
        $article = $this->buildDevelopingArticle();
        $article->setStatus(ArticleStatus::ARCHIVED);
        $primary = $this->mockSignal(400);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturn($primary);

        // Writer rejects the update (returns null) because the target is archived.
        $this->writer->method('write')->willReturn(null);

        // Guard + dispatch should never run.
        $this->guardPipeline->expects($this->never())->method('check');
        // TranslationPriorityDispatcher is a real instance; verify nothing was dispatched.

        $message = new WriteDevelopingStoryMessage(42, 400, [], 'full_flash');
        $this->assertNull(($this->handler)($message));
    }

    public function testMissingArticleLogsAndReturnsNull(): void
    {
        $this->articleRepository->method('find')->willReturn(null);
        $this->writer->expects($this->never())->method('write');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('write_developing_article_missing', $this->isArray());

        $message = new WriteDevelopingStoryMessage(999999, 1, [], 'full_flash');
        $this->assertNull(($this->handler)($message));
    }

    public function testUnknownVerdictTypeLogsErrorAndReturnsNull(): void
    {
        $this->articleRepository->method('find')->willReturn($this->buildDevelopingArticle());
        $this->signalRepository->method('find')->willReturn($this->mockSignal(1));

        $this->writer->expects($this->never())->method('write');
        $this->logger->expects($this->once())
            ->method('error')
            ->with('write_developing_unknown_verdict_type', $this->isArray());

        $message = new WriteDevelopingStoryMessage(42, 1, [], 'nonsense');
        $this->assertNull(($this->handler)($message));
    }

    public function testHandlerSwallowsWriterException(): void
    {
        $article = $this->buildDevelopingArticle();
        $primary = $this->mockSignal(700);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturn($primary);

        $this->writer->method('write')
            ->willThrowException(new \RuntimeException('writer exploded'));

        $this->guardPipeline->expects($this->never())->method('check');
        $this->escalationLogWriter->expects($this->never())->method('write');

        $this->logger->expects($this->once())
            ->method('error')
            ->with('write_developing_handler_failed', $this->callback(static function (array $ctx): bool {
                return isset($ctx['article_id'], $ctx['exception'], $ctx['error'])
                    && $ctx['article_id'] === 42
                    && $ctx['exception'] === \RuntimeException::class;
            }));

        $message = new WriteDevelopingStoryMessage(42, 700, [], 'full_flash');

        // Must NOT rethrow — matches VerifyClaimMessageHandler log-and-noop contract.
        $this->assertNull(($this->handler)($message));
        $this->assertCount(0, $this->dispatchedTranslations);
    }

    public function testHandlerSwallowsGuardPipelineException(): void
    {
        $article = $this->buildDevelopingArticle();
        $primary = $this->mockSignal(800);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->writer->method('write')->willReturn($article);

        $this->guardPipeline->method('check')
            ->willThrowException(new \LogicException('guard pipeline broken'));

        $this->escalationLogWriter->expects($this->never())->method('write');

        $this->logger->expects($this->once())
            ->method('error')
            ->with('write_developing_handler_failed', $this->callback(static function (array $ctx): bool {
                return isset($ctx['exception'])
                    && $ctx['exception'] === \LogicException::class;
            }));

        $message = new WriteDevelopingStoryMessage(42, 800, [], 'full_flash');
        $this->assertNull(($this->handler)($message));
        $this->assertCount(0, $this->dispatchedTranslations);
    }

    public function testEmergencyHaltShortCircuitsBeforeAnyDependencyIsTouched(): void
    {
        $haltedAppSettings = $this->createMock(AppSettingRepository::class);
        $haltedAppSettings->method('getBool')
            ->with('editorial.emergency_halt', false)
            ->willReturn(true);

        $handler = new WriteDevelopingStoryMessageHandler(
            $this->writer,
            $this->guardPipeline,
            $this->translationDispatcher,
            $this->escalationLogWriter,
            new GuardEscalationCategoryMapper(),
            $this->articleRepository,
            $this->signalRepository,
            $this->em,
            $haltedAppSettings,
            $this->writerThrottle,
            $this->messageBus,
            $this->logger,
        );

        // Short-circuit must happen before any repo/writer/guard/dispatch hits.
        $this->articleRepository->expects($this->never())->method('find');
        $this->signalRepository->expects($this->never())->method('find');
        $this->writer->expects($this->never())->method('write');
        $this->guardPipeline->expects($this->never())->method('check');
        $this->escalationLogWriter->expects($this->never())->method('write');
        $this->em->expects($this->never())->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('emergency_halt.triggered', $this->callback(static function (array $ctx): bool {
                return ($ctx['handler'] ?? null) === WriteDevelopingStoryMessageHandler::class
                    && ($ctx['message_class'] ?? null) === WriteDevelopingStoryMessage::class
                    && ($ctx['message_id_hint'] ?? null) === 5151;
            }));

        $result = $handler(new WriteDevelopingStoryMessage(5151, 700, [], 'full_flash'));

        $this->assertNull($result);
        $this->assertCount(0, $this->dispatchedTranslations);
    }

    public function testEmergencyHaltDisabledKeepsDelegatingToDeps(): void
    {
        // Sanity twin to emergency_halt=true: with flag=false (default in setUp),
        // the happy-path delegation chain still runs end-to-end.
        $article = $this->buildDevelopingArticle();
        $primary = $this->mockSignal(5152);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturn($primary);

        $this->writer->expects($this->once())
            ->method('write')
            ->willReturn($article);

        $this->guardPipeline->expects($this->once())
            ->method('check')
            ->willReturn(new GuardVerdict(passed: true));

        $result = ($this->handler)(new WriteDevelopingStoryMessage(42, 5152, [], 'full_flash'));

        $this->assertSame($article, $result);
        $this->assertCount(1, $this->dispatchedTranslations);
    }

    public function testApprovedEscalationBypassSkipsGuardPipelineAndReTranslates(): void
    {
        // T56.05 symmetric — DevelopingStory revision dispatched with
        // approvedEscalationLogId skips GuardPipeline and proceeds directly
        // to re-translation.
        $article = $this->buildDevelopingArticle();
        $primary = $this->mockSignal(910);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->writer->expects($this->once())->method('write')->willReturn($article);

        $this->guardPipeline->expects($this->never())->method('check');
        $this->escalationLogWriter->expects($this->never())->method('write');
        // Article is already public → no em->flush() required in bypass path.
        $this->em->expects($this->never())->method('flush');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('write_developing_published.bypass', $this->callback(static function (array $ctx): bool {
                return ($ctx['escalation_log_id'] ?? null) === 88
                    && ($ctx['article_id'] ?? null) === 42;
            }));

        $message = new WriteDevelopingStoryMessage(
            articleId: 42,
            primarySignalId: 910,
            supportingSignalIds: [],
            verdictType: 'full_flash',
            approvedEscalationLogId: 88,
        );
        $result = ($this->handler)($message);

        $this->assertSame($article, $result);
        // Re-translation still fires (editor-override still requires EN+RU).
        $this->assertCount(1, $this->dispatchedTranslations);
        $this->assertTrue($this->dispatchedTranslations[0]->forceRetranslate);
    }

    public function testNullApprovedEscalationLogIdPreservesGuardChain(): void
    {
        // Sanity twin for DevelopingStory — pipeline-emitted dispatch still
        // exercises GuardPipeline exactly once.
        $article = $this->buildDevelopingArticle();
        $primary = $this->mockSignal(911);

        $this->articleRepository->method('find')->willReturn($article);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->writer->method('write')->willReturn($article);

        $this->guardPipeline->expects($this->once())
            ->method('check')
            ->willReturn(new GuardVerdict(passed: true));

        $this->logger->expects($this->once())
            ->method('info')
            ->with('write_developing_published', $this->anything());

        $result = ($this->handler)(new WriteDevelopingStoryMessage(42, 911, [], 'full_flash'));

        $this->assertSame($article, $result);
        $this->assertCount(1, $this->dispatchedTranslations);
    }

    public function testThrottleBlockedReenqueuesWithDelayAndSkipsWriter(): void
    {
        // T56.06 symmetric — DevelopingStory blocked by WriterThrottle
        // redispatches with DelayStamp + skips writer/guard.
        $retryAfter = new \DateTimeImmutable('+45 seconds');
        $throttle = $this->createMock(WriterThrottle::class);
        $throttle->expects($this->once())
            ->method('consume')
            ->willReturn(new RateLimit(0, $retryAfter, false, 10));

        // Dedicated bus: capture only the re-dispatched WriteDevelopingStoryMessage.
        $throttleBus = $this->createMock(MessageBusInterface::class);
        /** @var list<array{message: object, stamps: list<mixed>}> $captured */
        $captured = [];
        $throttleBus->method('dispatch')
            ->willReturnCallback(function (object $m, array $stamps = []) use (&$captured): Envelope {
                $captured[] = ['message' => $m, 'stamps' => $stamps];

                return new Envelope($m);
            });

        $handler = new WriteDevelopingStoryMessageHandler(
            $this->writer,
            $this->guardPipeline,
            $this->translationDispatcher,
            $this->escalationLogWriter,
            new GuardEscalationCategoryMapper(),
            $this->articleRepository,
            $this->signalRepository,
            $this->em,
            $this->appSettings,
            $throttle,
            $throttleBus,
            $this->logger,
        );

        // Downstream MUST stay idle on throttle reject.
        $this->articleRepository->expects($this->never())->method('find');
        $this->signalRepository->expects($this->never())->method('find');
        $this->writer->expects($this->never())->method('write');
        $this->guardPipeline->expects($this->never())->method('check');

        $this->logger->expects($this->once())
            ->method('info')
            ->with('throttle.blocked', $this->callback(static function (array $ctx): bool {
                return ($ctx['handler'] ?? null) === WriteDevelopingStoryMessageHandler::class
                    && ($ctx['message_id_hint'] ?? null) === 42
                    && ($ctx['limit'] ?? null) === 10;
            }));

        $result = $handler(new WriteDevelopingStoryMessage(42, 6200, [], 'full_flash'));

        $this->assertNull($result);
        $this->assertCount(1, $captured, 'redispatched exactly once');
        $this->assertInstanceOf(WriteDevelopingStoryMessage::class, $captured[0]['message']);
        $this->assertCount(1, $captured[0]['stamps']);
        $this->assertInstanceOf(DelayStamp::class, $captured[0]['stamps'][0]);
        $this->assertGreaterThanOrEqual(1_000, $captured[0]['stamps'][0]->getDelay());
        $this->assertLessThanOrEqual(46_000, $captured[0]['stamps'][0]->getDelay());
    }

    private function buildDevelopingArticle(): Article
    {
        $article = new Article();
        $article->setTitle('Developing story');
        $article->setLead('Seed lead');
        $article->setContent('Seed content');
        $article->setStatus(ArticleStatus::PUBLISHED);
        $article->setArticleType(ArticleType::DEVELOPING_STORY);
        $article->setRevisionCount(2);

        // TranslationPriorityDispatcher::dispatch bails out early when the
        // Article has no id, so seed one via reflection for tests that
        // expect the dispatch path to fire.
        $ref = new \ReflectionProperty(Article::class, 'id');
        $ref->setValue($article, 42);

        return $article;
    }

    private function mockSignal(int $id): SourceSignal&MockObject
    {
        $verifiedSource = $this->createMock(VerifiedSource::class);
        $signal = $this->createMock(SourceSignal::class);
        $signal->method('getId')->willReturn($id);
        $signal->method('getVerifiedSource')->willReturn($verifiedSource);
        $signal->method('getClaimGraphSnapshot')->willReturn(null);

        return $signal;
    }
}
