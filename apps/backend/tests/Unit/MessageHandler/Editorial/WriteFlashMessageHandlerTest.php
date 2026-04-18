<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Editorial;

use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Topic;
use App\Enum\ArticleStatus;
use App\Enum\ArticleType;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\Editorial\VerdictType;
use App\Message\Editorial\WriteFlashMessage;
use App\MessageHandler\Editorial\WriteFlashMessageHandler;
use App\Repository\ArticleRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Repository\TopicRepository;
use App\Service\Editorial\Escalation\EscalationLogWriter;
use App\Service\Editorial\Guard\GuardEscalationCategoryMapper;
use App\Service\Editorial\Guard\GuardPipelineInterface;
use App\Service\Editorial\Guard\GuardVerdict;
use App\Service\Editorial\Guard\LegalGuard;
use App\Service\Editorial\PostApprovalDispatcher;
use App\Service\Editorial\Writer\FlashWriter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see WriteFlashMessageHandler} (Sprint 55 T55.3 + T55.9 extensions).
 *
 * Covers the three guard-verdict branches (pass / flag / escalate), idempotency,
 * and the defensive no-op contracts on missing inputs.
 */
class WriteFlashMessageHandlerTest extends TestCase
{
    private FlashWriter&MockObject $flashWriter;
    private GuardPipelineInterface&MockObject $guardPipeline;
    private PostApprovalDispatcher&MockObject $postApprovalDispatcher;
    private EscalationLogWriter&MockObject $escalationLogWriter;
    private SourceSignalRepository&MockObject $signalRepository;
    private ArticleRepository&MockObject $articleRepository;
    private TopicRepository&MockObject $topicRepository;
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;
    private WriteFlashMessageHandler $handler;

    protected function setUp(): void
    {
        $this->flashWriter = $this->createMock(FlashWriter::class);
        $this->guardPipeline = $this->createMock(GuardPipelineInterface::class);
        $this->postApprovalDispatcher = $this->createMock(PostApprovalDispatcher::class);
        $this->escalationLogWriter = $this->createMock(EscalationLogWriter::class);
        $this->signalRepository = $this->createMock(SourceSignalRepository::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->topicRepository = $this->createMock(TopicRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new WriteFlashMessageHandler(
            $this->flashWriter,
            $this->guardPipeline,
            $this->postApprovalDispatcher,
            $this->escalationLogWriter,
            new GuardEscalationCategoryMapper(),
            $this->signalRepository,
            $this->articleRepository,
            $this->topicRepository,
            $this->em,
            $this->logger,
        );
    }

    public function testGuardPassFlipsPublishedLocalesAndDispatches(): void
    {
        $primary = $this->mockSignal(100);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->articleRepository->method('findOneBy')->willReturn(null);
        $this->topicRepository->method('find')->willReturn(null);

        $article = $this->buildWriterArticle();
        $this->flashWriter->method('write')->willReturn($article);

        $this->guardPipeline->expects($this->once())
            ->method('check')
            ->willReturn(new GuardVerdict(passed: true));

        $this->em->expects($this->once())->method('flush');

        $this->postApprovalDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->identicalTo($article), null, 'ro');

        $this->escalationLogWriter->expects($this->never())->method('write');

        $message = new WriteFlashMessage(100, [], 'full_flash');

        $result = ($this->handler)($message);

        $this->assertSame($article, $result);
        // Writer emitted article with publishedLocales=[]; handler flipped to ['ro'].
        $this->assertSame(['ro'], $article->getPublishedLocales());
    }

    public function testGuardEscalationCodeArchivesAndInsertsLog(): void
    {
        $primary = $this->mockSignal(200);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->articleRepository->method('findOneBy')->willReturn(null);

        $article = $this->buildWriterArticle();
        $this->flashWriter->method('write')->willReturn($article);

        $this->guardPipeline->expects($this->once())
            ->method('check')
            ->willReturn(new GuardVerdict(
                passed: false,
                failures: ['[legal:defamation:high] risc grav'],
                warnings: [],
                escalationCode: LegalGuard::ESCALATION_CODE_CATEGORY_6,
            ));

        $this->em->expects($this->once())->method('flush');

        $this->escalationLogWriter->expects($this->once())
            ->method('write')
            ->with(
                EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION,
                $this->callback(function (array $snapshot): bool {
                    return isset($snapshot['primary_signal_id'])
                        && $snapshot['primary_signal_id'] === 200;
                }),
                $this->isArray(),
            );

        $this->postApprovalDispatcher->expects($this->never())->method('dispatch');

        $message = new WriteFlashMessage(200, [], 'full_flash');
        ($this->handler)($message);

        $this->assertSame(ArticleStatus::ARCHIVED, $article->getStatus());
        // publishedLocales stays empty — article never becomes public.
        $this->assertSame([], $article->getPublishedLocales());
    }

    public function testGuardFlagLeavesArticleNewAndInvisible(): void
    {
        $primary = $this->mockSignal(300);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->articleRepository->method('findOneBy')->willReturn(null);

        $article = $this->buildWriterArticle();
        $article->setInternalSummary('[writer_meta] initial');
        $this->flashWriter->method('write')->willReturn($article);

        $this->guardPipeline->expects($this->once())
            ->method('check')
            ->willReturn(new GuardVerdict(
                passed: false,
                failures: ['[diacritics:title] cedilla at offset 5'],
                warnings: [],
                escalationCode: null,
            ));

        $this->em->expects($this->once())->method('flush');

        $this->postApprovalDispatcher->expects($this->never())->method('dispatch');
        $this->escalationLogWriter->expects($this->never())->method('write');

        $message = new WriteFlashMessage(300, [], 'full_flash');
        ($this->handler)($message);

        // Article stays NEW + invisible (publishedLocales empty).
        $this->assertSame(ArticleStatus::NEW, $article->getStatus());
        $this->assertSame([], $article->getPublishedLocales());
        // internalSummary carries both the writer_meta prefix + guard_flag suffix.
        $summary = $article->getInternalSummary() ?? '';
        $this->assertStringContainsString('[writer_meta]', $summary);
        $this->assertStringContainsString('[guard_flag:', $summary);
        $this->assertStringContainsString('[diacritics:title]', $summary);
    }

    public function testIdempotencyShortCircuitsWhenArticleExists(): void
    {
        $primary = $this->mockSignal(400);
        $this->signalRepository->method('find')->willReturn($primary);

        $existing = $this->buildWriterArticle();
        $this->articleRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['originalSourceSignal' => $primary])
            ->willReturn($existing);

        // Writer must NOT run again.
        $this->flashWriter->expects($this->never())->method('write');
        $this->guardPipeline->expects($this->never())->method('check');
        $this->postApprovalDispatcher->expects($this->never())->method('dispatch');

        $message = new WriteFlashMessage(400, [], 'full_flash');
        $result = ($this->handler)($message);

        $this->assertSame($existing, $result);
    }

    public function testMissingPrimarySignalLogsAndReturnsNull(): void
    {
        $this->signalRepository->method('find')->willReturn(null);
        $this->flashWriter->expects($this->never())->method('write');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('write_flash_primary_signal_missing', $this->isArray());

        $message = new WriteFlashMessage(999, [], 'full_flash');
        $this->assertNull(($this->handler)($message));
    }

    public function testUnknownVerdictTypeLogsErrorAndReturnsNull(): void
    {
        $primary = $this->mockSignal(1);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->articleRepository->method('findOneBy')->willReturn(null);

        $this->flashWriter->expects($this->never())->method('write');
        $this->logger->expects($this->once())
            ->method('error')
            ->with('write_flash_unknown_verdict_type', $this->isArray());

        $message = new WriteFlashMessage(1, [], 'invented_verdict');
        $this->assertNull(($this->handler)($message));
    }

    public function testUnavailableEscalationCodeMapsToCat6Fallback(): void
    {
        $primary = $this->mockSignal(500);
        $this->signalRepository->method('find')->willReturn($primary);
        $this->articleRepository->method('findOneBy')->willReturn(null);

        $article = $this->buildWriterArticle();
        $this->flashWriter->method('write')->willReturn($article);

        // LegalGuard emits LEGAL_GUARD_UNAVAILABLE when fail-closed on Cat6
        // and LLM is unavailable. Mapper must still route that to CATEGORY_6.
        $this->guardPipeline->method('check')->willReturn(new GuardVerdict(
            passed: false,
            failures: ['[legal] Category 6 pattern detected but LLM unavailable'],
            escalationCode: LegalGuard::ESCALATION_CODE_UNAVAILABLE,
        ));

        $this->escalationLogWriter->expects($this->once())
            ->method('write')
            ->with(EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION, $this->isArray(), $this->isArray());

        $message = new WriteFlashMessage(500, [], 'full_flash');
        ($this->handler)($message);
    }

    private function buildWriterArticle(): Article
    {
        $article = new Article();
        $article->setTitle('Mock title');
        $article->setLead('Mock lead');
        $article->setContent('Mock content');
        $article->setStatus(ArticleStatus::NEW);
        $article->setArticleType(ArticleType::FLASH);
        // Writer-emitted Article is invisible by default (Sprint 55 T55.9 refactor).
        $article->setPublishedLocales([]);

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
