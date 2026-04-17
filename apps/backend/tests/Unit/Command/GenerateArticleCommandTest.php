<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\GenerateArticleCommand;
use App\Dto\Editorial\ArticleDraft;
use App\Entity\PressRelease;
use App\Entity\StoryCluster;
use App\Entity\Topic;
use App\Repository\AppSettingRepository;
use App\Repository\PressReleaseRepository;
use App\Repository\StoryClusterRepository;
use App\Message\GenerateTopicArticleMessage;
use App\Repository\TopicBriefingRepository;
use App\Repository\TopicRepository;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\ArticleWriterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class GenerateArticleCommandTest extends TestCase
{
    private StoryClusterRepository&MockObject $clusterRepository;
    private TopicRepository&MockObject $topicRepository;
    private PressReleaseRepository&MockObject $pressReleaseRepository;
    private AppSettingRepository&MockObject $appSettings;
    private EntityManagerInterface&MockObject $em;
    private ArticleWriterService&MockObject $writerService;
    private MessageBusInterface&MockObject $messageBus;
    private GenerateArticleCommand $command;

    protected function setUp(): void
    {
        $this->clusterRepository = $this->createMock(StoryClusterRepository::class);
        $this->topicRepository = $this->createMock(TopicRepository::class);
        $this->pressReleaseRepository = $this->createMock(PressReleaseRepository::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->writerService = $this->createMock(ArticleWriterService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        // MessageBus dispatch returns Envelope — stub a minimal one for default
        $this->messageBus->method('dispatch')->willReturnCallback(
            static fn (object $msg): Envelope => new Envelope($msg),
        );

        $this->command = new GenerateArticleCommand(
            $this->writerService,
            $this->clusterRepository,
            $this->em,
            $this->appSettings,
            $this->topicRepository,
            $this->pressReleaseRepository,
            $this->messageBus,
        );
    }

    #[Test]
    public function commandHasCorrectName(): void
    {
        $this->assertSame('app:generate-article', $this->command->getName());
    }

    #[Test]
    public function commandHasDescription(): void
    {
        $this->assertNotEmpty($this->command->getDescription());
    }

    // ────────────────────────────────────────────────────────────────────
    //  Legacy cluster path — backward compat preserved
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function legacyPathFailsWhenClusterNotFound(): void
    {
        $this->clusterRepository->method('find')->willReturn(null);

        $tester = $this->makeTester();
        $tester->execute(['cluster-id' => '9999']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    #[Test]
    public function legacyPathRejectsLowScoreCluster(): void
    {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Low score cluster');

        $this->clusterRepository->method('find')->willReturn($cluster);

        $tester = $this->makeTester();
        $tester->execute(['cluster-id' => '1']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('below minimum', $tester->getDisplay());
    }

    #[Test]
    public function legacyPathDryRunDoesNotPersist(): void
    {
        $cluster = $this->makeRichCluster();
        $this->clusterRepository->method('find')->willReturn($cluster);

        // Eligibility passes
        $this->writerService->method('isEligibleForAiGeneration')->willReturn([
            'eligible' => true, 'reason' => null, 'sourceCount' => 5, 'avgLength' => 2000,
        ]);
        // Writer returns null (Gemini failure simulated)
        $this->writerService->method('generateArticle')->willReturn(null);

        $this->em->expects($this->never())->method('persist');

        $tester = $this->makeTester();
        $tester->execute(['cluster-id' => '1', '--dry-run' => true]);

        // Writer null → command returns FAILURE per legacy contract
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    // ────────────────────────────────────────────────────────────────────
    //  Topic + Window path — Sprint 52, ADR-019 D2
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function topicWindowPathErrorsWhenFeatureFlagFalse(): void
    {
        $this->stubAppSettings(useTopicWindow: false);

        $tester = $this->makeTester();
        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('use_topic_window', $tester->getDisplay());
    }

    #[Test]
    public function topicWindowPathReportsNoWorkWhenZeroEligibleTopics(): void
    {
        $this->stubAppSettings(useTopicWindow: true);
        $this->topicRepository->method('findActiveWithUnprocessedPressReleasesSince')->willReturn([]);

        $tester = $this->makeTester();
        $tester->execute(['--sync' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('No eligible topics', $tester->getDisplay());
    }

    #[Test]
    public function topicWindowPathSkipsIneligibleTopicsAndContinues(): void
    {
        $this->stubAppSettings(useTopicWindow: true);
        $topic = $this->makeTopic('skip-me');

        $this->topicRepository->method('findActiveWithUnprocessedPressReleasesSince')
            ->willReturn([$topic]);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn([]);
        $this->writerService->method('isEligibleForTopicWindow')->willReturn([
            'eligible' => false,
            'reasons' => ['Too few press releases (0, need 1)'],
            'prCount' => 0,
            'avgConfidence' => 0.0,
            'avgContentLength' => 0,
        ]);
        $this->writerService->expects($this->never())->method('writeArticleFromTopicWindow');
        $this->em->expects($this->never())->method('persist');

        $tester = $this->makeTester();
        $tester->execute(['--sync' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('INELIGIBLE', $tester->getDisplay());
    }

    #[Test]
    public function topicWindowPathSkipsTopicWhenWriterReturnsNull(): void
    {
        $this->stubAppSettings(useTopicWindow: true);
        $topic = $this->makeTopic('writer-null');
        $prs = $this->makePressReleases(3);

        $this->topicRepository->method('findActiveWithUnprocessedPressReleasesSince')
            ->willReturn([$topic]);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn($prs);
        $this->writerService->method('isEligibleForTopicWindow')->willReturn([
            'eligible' => true, 'reasons' => [], 'prCount' => 3, 'avgConfidence' => 0.9, 'avgContentLength' => 2000,
        ]);
        $this->writerService->method('writeArticleFromTopicWindow')->willReturn(null);

        $this->em->expects($this->never())->method('persist');

        $tester = $this->makeTester();
        $tester->execute(['--sync' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode(), 'Writer null is per-topic; batch must continue');
        $this->assertStringContainsString('WRITER FAILED', $tester->getDisplay());
    }

    #[Test]
    public function topicWindowPathPersistsDraftAsPressReleaseInRealRun(): void
    {
        $this->stubAppSettings(useTopicWindow: true);
        $topic = $this->makeTopic('happy-path');
        $prs = $this->makePressReleases(3);
        $draft = $this->makeDraft(topicId: $topic->getId());

        $this->topicRepository->method('findActiveWithUnprocessedPressReleasesSince')
            ->willReturn([$topic]);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn($prs);
        $this->writerService->method('isEligibleForTopicWindow')->willReturn([
            'eligible' => true, 'reasons' => [], 'prCount' => 3, 'avgConfidence' => 0.9, 'avgContentLength' => 2000,
        ]);
        $this->writerService->method('writeArticleFromTopicWindow')->willReturn($draft);

        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(PressRelease::class));
        $this->em->expects($this->once())->method('flush');

        $tester = $this->makeTester();
        $tester->execute(['--sync' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('OK: PR', $tester->getDisplay());
    }

    #[Test]
    public function topicWindowPathDryRunDoesNotPersist(): void
    {
        $this->stubAppSettings(useTopicWindow: true);
        $topic = $this->makeTopic('dry-run');
        $prs = $this->makePressReleases(3);
        $draft = $this->makeDraft(topicId: $topic->getId());

        $this->topicRepository->method('findActiveWithUnprocessedPressReleasesSince')
            ->willReturn([$topic]);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn($prs);
        $this->writerService->method('isEligibleForTopicWindow')->willReturn([
            'eligible' => true, 'reasons' => [], 'prCount' => 3, 'avgConfidence' => 0.9, 'avgContentLength' => 2000,
        ]);
        $this->writerService->method('writeArticleFromTopicWindow')->willReturn($draft);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $tester = $this->makeTester();
        $tester->execute(['--sync' => true, '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('DRY:', $tester->getDisplay());
    }

    #[Test]
    public function topicWindowPathLimitCapsTopicsProcessed(): void
    {
        $this->stubAppSettings(useTopicWindow: true);
        $topics = [
            $this->makeTopic('limit-1'),
            $this->makeTopic('limit-2'),
            $this->makeTopic('limit-3'),
        ];

        $this->topicRepository->method('findActiveWithUnprocessedPressReleasesSince')
            ->willReturn($topics);
        $this->pressReleaseRepository->method('findByTopicInWindow')->willReturn([]);
        $this->writerService->method('isEligibleForTopicWindow')->willReturn([
            'eligible' => false, 'reasons' => ['Too few PRs'], 'prCount' => 0, 'avgConfidence' => 0.0, 'avgContentLength' => 0,
        ]);

        $tester = $this->makeTester();
        $tester->execute(['--sync' => true, '--limit' => '2']);

        $display = $tester->getDisplay();
        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('processing top 2', $display);
        // Only first two topics should be in display
        $this->assertStringContainsString('limit-1', $display);
        $this->assertStringContainsString('limit-2', $display);
        $this->assertStringNotContainsString('limit-3', $display);
    }

    #[Test]
    public function topicWindowPathDefaultDispatchesGenerateTopicArticleMessage(): void
    {
        $this->stubAppSettings(useTopicWindow: true);
        $topics = [
            $this->makeTopic('async-1'),
            $this->makeTopic('async-2'),
        ];

        $this->topicRepository->method('findActiveWithUnprocessedPressReleasesSince')
            ->willReturn($topics);

        // No sync work in async mode: writer + repo + persist must NOT fire
        $this->writerService->expects($this->never())->method('isEligibleForTopicWindow');
        $this->writerService->expects($this->never())->method('writeArticleFromTopicWindow');
        $this->pressReleaseRepository->expects($this->never())->method('findByTopicInWindow');
        $this->em->expects($this->never())->method('persist');

        // Expect exactly one dispatch per topic
        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->with($this->isInstanceOf(GenerateTopicArticleMessage::class))
            ->willReturnCallback(static fn (object $msg) => new Envelope($msg));

        $tester = $this->makeTester();
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('async (dispatch via briefing transport)', $tester->getDisplay());
        $this->assertStringContainsString('DISPATCHED', $tester->getDisplay());
    }

    #[Test]
    public function topicWindowPathAsyncDryRunDoesNotDispatch(): void
    {
        $this->stubAppSettings(useTopicWindow: true);
        $topics = [$this->makeTopic('async-dry-run')];

        $this->topicRepository->method('findActiveWithUnprocessedPressReleasesSince')
            ->willReturn($topics);

        $this->messageBus->expects($this->never())->method('dispatch');

        $tester = $this->makeTester();
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('DRY: would dispatch', $tester->getDisplay());
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────────

    private function makeTester(): CommandTester
    {
        $app = new Application();
        $app->addCommand($this->command);

        return new CommandTester($this->command);
    }

    private function stubAppSettings(bool $useTopicWindow): void
    {
        $this->appSettings->method('getBool')->willReturnCallback(
            static fn (string $key, bool $default = false): bool => match ($key) {
                'article_generation.use_topic_window' => $useTopicWindow,
                default => $default,
            },
        );
        $this->appSettings->method('getInt')->willReturnCallback(
            static fn (string $key, int $default = 0): int => match ($key) {
                'article_generation.window_hours' => 24,
                'article_generation.min_pr_count' => 1,
                'article_generation.min_avg_content_length' => 1500,
                default => $default,
            },
        );
        $this->appSettings->method('getFloat')->willReturnCallback(
            static fn (string $key, float $default = 0.0): float => match ($key) {
                'article_generation.min_topic_relevance' => 2.0,
                default => $default,
            },
        );
    }

    private function makeTopic(string $slug): Topic
    {
        $topic = new Topic();
        $topic->setTranslatableLocale('ro');
        $topic->setSlug($slug);
        $topic->setTitle('Topic ' . $slug);
        $topic->setIsActive(true);

        $idProp = new \ReflectionProperty(Topic::class, 'id');
        $idProp->setValue($topic, crc32($slug));

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
            $pr->setTitle('Mock PR ' . $i);
            $pr->setContent(str_repeat('content ', 200));
            $pr->setCategorySlug('externe');

            $idProp = new \ReflectionProperty(PressRelease::class, 'id');
            $idProp->setValue($pr, $i + 100);

            $prs[] = $pr;
        }

        return $prs;
    }

    private function makeDraft(?int $topicId): ArticleDraft
    {
        return new ArticleDraft(
            titleRo: 'Generated title',
            leadRo: 'Generated lead text in Romanian.',
            contentRo: str_repeat('Generated content paragraph. ', 30),
            metaDescription: 'Meta description',
            suggestedTags: ['tag1', 'tag2'],
            clusterId: null,
            confidenceScore: 0.85,
            sourcePressReleaseIds: [100, 101, 102],
            rawPrompt: 'prompt text',
            rawResponse: '{}',
            topicId: $topicId,
        );
    }

    private function makeRichCluster(): StoryCluster
    {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Good cluster');

        $ref = new \ReflectionProperty($cluster, 'importanceScore');
        $ref->setValue($cluster, 0.90);

        for ($i = 0; $i < 5; $i++) {
            $pr = new PressRelease();
            $pr->setTitle("PR #{$i}");
            $pr->setContent(str_repeat('Long content. ', 150));
            $pr->setCategorySlug('externe');
            $cluster->addPressRelease($pr);
        }
        $cluster->recalculateCounts();

        return $cluster;
    }
}

