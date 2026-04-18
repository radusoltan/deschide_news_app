<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Dto\Editorial\ArticleDraft;
use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Message\GenerateTopicArticleMessage;
use App\MessageHandler\GenerateTopicArticleHandler;
use App\Repository\PressReleaseRepository;
use App\Service\Editorial\ArticleWriterService;
use App\Service\Verification\SemanticVerifierService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class GenerateTopicArticleHandlerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private PressReleaseRepository&MockObject $pressReleaseRepository;
    private ArticleWriterService&MockObject $writerService;
    private SemanticVerifierService&MockObject $semanticVerifier;
    private GenerateTopicArticleHandler $handler;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->pressReleaseRepository = $this->createMock(PressReleaseRepository::class);
        $this->writerService = $this->createMock(ArticleWriterService::class);
        $this->semanticVerifier = $this->createMock(SemanticVerifierService::class);

        $this->handler = new GenerateTopicArticleHandler(
            $this->em,
            $this->pressReleaseRepository,
            $this->writerService,
            $this->semanticVerifier,
            new NullLogger(),
        );
    }

    #[Test]
    public function returnsGracefullyWhenTopicNotFound(): void
    {
        $this->em->method('find')->with(Topic::class, 999)->willReturn(null);

        $this->writerService->expects($this->never())->method('isEligibleForTopicWindow');
        $this->em->expects($this->never())->method('persist');

        ($this->handler)($this->makeMessage(999));
    }

    #[Test]
    public function returnsGracefullyWhenTopicInactive(): void
    {
        $topic = $this->makeTopic(isActive: false);
        $this->em->method('find')->willReturn($topic);

        $this->writerService->expects($this->never())->method('isEligibleForTopicWindow');
        $this->em->expects($this->never())->method('persist');

        ($this->handler)($this->makeMessage(42));
    }

    #[Test]
    public function returnsGracefullyWhenNoPressReleasesInWindow(): void
    {
        $topic = $this->makeTopic();
        $this->em->method('find')->willReturn($topic);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn([]);

        $this->writerService->expects($this->never())->method('isEligibleForTopicWindow');
        $this->em->expects($this->never())->method('persist');

        ($this->handler)($this->makeMessage(42));
    }

    #[Test]
    public function returnsGracefullyWhenIneligible(): void
    {
        $topic = $this->makeTopic();
        $this->em->method('find')->willReturn($topic);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn($this->makePressReleases(2));
        $this->writerService->method('isEligibleForTopicWindow')->willReturn([
            'eligible' => false,
            'reasons' => ['Too few PRs'],
            'prCount' => 2,
            'avgConfidence' => 0.4,
            'avgContentLength' => 1000,
        ]);

        $this->writerService->expects($this->never())->method('writeArticleFromTopicWindow');
        $this->em->expects($this->never())->method('persist');

        ($this->handler)($this->makeMessage(42));
    }

    #[Test]
    public function returnsGracefullyWhenWriterReturnsNull(): void
    {
        $topic = $this->makeTopic();
        $prs = $this->makePressReleases(3);
        $this->em->method('find')->willReturn($topic);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn($prs);
        $this->writerService->method('isEligibleForTopicWindow')->willReturn([
            'eligible' => true, 'reasons' => [], 'prCount' => 3, 'avgConfidence' => 0.9, 'avgContentLength' => 2000,
        ]);
        $this->semanticVerifier->method('findDuplicatePairsInTopicWindow')->willReturn([]);
        $this->writerService->method('writeArticleFromTopicWindow')->willReturn(null);

        $this->em->expects($this->never())->method('persist');

        ($this->handler)($this->makeMessage(42));
    }

    #[Test]
    public function persistsPressReleaseOnHappyPath(): void
    {
        $topic = $this->makeTopic();
        $prs = $this->makePressReleases(3);
        $this->em->method('find')->willReturn($topic);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn($prs);
        $this->writerService->method('isEligibleForTopicWindow')->willReturn([
            'eligible' => true, 'reasons' => [], 'prCount' => 3, 'avgConfidence' => 0.9, 'avgContentLength' => 2000,
        ]);
        $this->semanticVerifier->method('findDuplicatePairsInTopicWindow')->willReturn([]);
        $this->writerService->method('writeArticleFromTopicWindow')->willReturn($this->makeDraft());

        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(PressRelease::class));
        $this->em->expects($this->once())->method('flush');

        ($this->handler)($this->makeMessage(42));
    }

    #[Test]
    public function skipsWhenSemanticDedupExceedsThreshold(): void
    {
        $topic = $this->makeTopic();
        // 4 PRs, 3 of them in duplicate pairs → 75% > 50% threshold
        $prs = $this->makePressReleases(4);
        $this->em->method('find')->willReturn($topic);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn($prs);
        $this->writerService->method('isEligibleForTopicWindow')->willReturn([
            'eligible' => true, 'reasons' => [], 'prCount' => 4, 'avgConfidence' => 0.9, 'avgContentLength' => 2000,
        ]);
        // Pairs cover ids 100, 101, 102 (3 of 4 PRs) → ratio 0.75
        $this->semanticVerifier->method('findDuplicatePairsInTopicWindow')->willReturn([
            [100, 101, 0.95],
            [100, 102, 0.91],
        ]);

        $this->writerService->expects($this->never())->method('writeArticleFromTopicWindow');
        $this->em->expects($this->never())->method('persist');

        ($this->handler)($this->makeMessage(42));
    }

    #[Test]
    public function proceedsWhenSemanticDedupBelowThreshold(): void
    {
        $topic = $this->makeTopic();
        // 4 PRs, only 2 in pair → 50% — equals threshold (>0.5 is the gate, so 0.5 passes)
        $prs = $this->makePressReleases(4);
        $this->em->method('find')->willReturn($topic);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn($prs);
        $this->writerService->method('isEligibleForTopicWindow')->willReturn([
            'eligible' => true, 'reasons' => [], 'prCount' => 4, 'avgConfidence' => 0.9, 'avgContentLength' => 2000,
        ]);
        $this->semanticVerifier->method('findDuplicatePairsInTopicWindow')->willReturn([
            [100, 101, 0.95],
        ]);
        $this->writerService->expects($this->once())
            ->method('writeArticleFromTopicWindow')
            ->willReturn($this->makeDraft());

        $this->em->expects($this->once())->method('persist');

        ($this->handler)($this->makeMessage(42));
    }

    #[Test]
    public function semanticVerifierFailOpenStillProceedsToWriter(): void
    {
        $topic = $this->makeTopic();
        $prs = $this->makePressReleases(3);
        $this->em->method('find')->willReturn($topic);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn($prs);
        $this->writerService->method('isEligibleForTopicWindow')->willReturn([
            'eligible' => true, 'reasons' => [], 'prCount' => 3, 'avgConfidence' => 0.9, 'avgContentLength' => 2000,
        ]);
        // Verifier returns [] — fail-open contract (no dups detected OR LLM error)
        $this->semanticVerifier->method('findDuplicatePairsInTopicWindow')->willReturn([]);
        $this->writerService->expects($this->once())
            ->method('writeArticleFromTopicWindow')
            ->willReturn($this->makeDraft());

        $this->em->expects($this->once())->method('persist');

        ($this->handler)($this->makeMessage(42));
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────────

    private function makeMessage(int $topicId): GenerateTopicArticleMessage
    {
        return new GenerateTopicArticleMessage(
            $topicId,
            new \DateTimeImmutable('-24 hours'),
            new \DateTimeImmutable(),
        );
    }

    private function makeTopic(bool $isActive = true): Topic
    {
        $topic = new Topic();
        $topic->setTranslatableLocale('ro');
        $topic->setSlug('handler-test');
        $topic->setTitle('Handler Test Topic');
        $topic->setIsActive($isActive);

        $idProp = new \ReflectionProperty(Topic::class, 'id');
        $idProp->setValue($topic, 42);

        return $topic;
    }

    /**
     * @return PressRelease[]
     */
    private function makePressReleases(int $count): array
    {
        $prs = [];
        for ($i = 0; $i < $count; $i++) {
            $pr = new PressRelease();
            $pr->setTitle('PR ' . $i);
            $pr->setContent(str_repeat('content ', 200));
            $pr->setCategorySlug('externe');

            $idProp = new \ReflectionProperty(PressRelease::class, 'id');
            $idProp->setValue($pr, 100 + $i);

            $prs[] = $pr;
        }

        return $prs;
    }

    private function makeDraft(): ArticleDraft
    {
        return new ArticleDraft(
            titleRo: 'Handler-test article',
            leadRo: 'Lead text.',
            contentRo: str_repeat('Content paragraph. ', 30),
            metaDescription: 'Meta',
            suggestedTags: [],
            confidenceScore: 0.85,
            sourcePressReleaseIds: [100, 101, 102],
            rawPrompt: 'p',
            rawResponse: '{}',
            topicId: 42,
        );
    }
}
