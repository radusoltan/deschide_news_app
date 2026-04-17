<?php

declare(strict_types=1);

namespace App\Command;

use App\Dto\Editorial\ArticleDraft;
use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Message\GenerateTopicArticleMessage;
use App\Repository\AppSettingRepository;
use App\Repository\PressReleaseRepository;
use App\Repository\StoryClusterRepository;
use App\Repository\TopicRepository;
use App\Service\Editorial\ArticleWriterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Generate AI articles via either:
 *  - the Sprint 52 Topic + Window path (default when no cluster-id and the
 *    `article_generation.use_topic_window` AppSetting is true)
 *  - the legacy StoryCluster path (when a cluster-id argument is provided
 *    OR when the feature flag is false). Removed in T52.12 per ADR-019 D6.
 *
 * Phase 1 (T52.6) is synchronous: per eligible topic, the writer is invoked
 * directly in the CLI process. T52.7 introduces async dispatch via
 * GenerateTopicArticleMessage and flips the default to --async.
 */
#[AsCommand(
    name: 'app:generate-article',
    description: 'Generate AI articles from eligible topics (default) or a specific StoryCluster (legacy).',
)]
class GenerateArticleCommand extends Command
{
    private const LEGACY_MIN_SCORE = 0.75;

    public function __construct(
        private readonly ArticleWriterService $writerService,
        private readonly StoryClusterRepository $clusterRepository,
        private readonly EntityManagerInterface $em,
        private readonly AppSettingRepository $appSettings,
        private readonly TopicRepository $topicRepository,
        private readonly PressReleaseRepository $pressReleaseRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'cluster-id',
                InputArgument::OPTIONAL,
                'StoryCluster ID — when provided, runs the @deprecated cluster path regardless of the feature flag. When omitted, the topic-window path runs (subject to article_generation.use_topic_window).',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview only, do not persist any PressRelease')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip eligibility checks (legacy cluster path only)')
            ->addOption(
                'min-score',
                null,
                InputOption::VALUE_REQUIRED,
                'Minimum cluster importance score (legacy cluster path only)',
                (string) self::LEGACY_MIN_SCORE,
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of topics to process in topic-window mode (0 = unlimited)',
                '0',
            )
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Force synchronous in-process generation (default in production is async dispatch via GenerateTopicArticleMessage)')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Async dispatch via GenerateTopicArticleMessage (this is the default since T52.7 — flag is documented for clarity, no-op if --sync is also set)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $clusterIdArg = $input->getArgument('cluster-id');
        $dryRun = (bool) $input->getOption('dry-run');

        // Cluster-id provided → legacy path (always, regardless of flag).
        if ($clusterIdArg !== null) {
            return $this->executeLegacyClusterPath(
                $io,
                (int) $clusterIdArg,
                $dryRun,
                (bool) $input->getOption('force'),
                (float) $input->getOption('min-score'),
            );
        }

        // No cluster-id → topic-window path (subject to feature flag).
        $useTopicWindow = $this->appSettings->getBool('article_generation.use_topic_window', true);
        if (!$useTopicWindow) {
            $io->error(
                'article_generation.use_topic_window is false. '
                . 'Either provide a cluster-id argument to use the legacy path, '
                . 'or enable the feature flag in AppSettings.',
            );

            return Command::FAILURE;
        }

        $sync = (bool) $input->getOption('sync');

        return $this->executeTopicWindowPath(
            $io,
            $dryRun,
            (int) $input->getOption('limit'),
            $sync,
        );
    }

    // ────────────────────────────────────────────────────────────────────
    //  Sprint 52 — Topic + Window path (ADR-019 D2, T52.6 sync phase)
    // ────────────────────────────────────────────────────────────────────

    private function executeTopicWindowPath(SymfonyStyle $io, bool $dryRun, int $limit, bool $sync): int
    {
        $io->title('Generate Articles — Topic + Window (Sprint 52, ADR-019 D2)');

        $windowHours = $this->appSettings->getInt('article_generation.window_hours', 24);
        $minPrCount = $this->appSettings->getInt('article_generation.min_pr_count', 1);
        $minRelevance = $this->appSettings->getFloat('article_generation.min_topic_relevance', 2.0);
        $now = new \DateTimeImmutable();
        $windowStart = $now->modify(sprintf('-%d hours', $windowHours));
        $windowEnd = $now;

        $modeLabel = $sync ? 'sync (in-process)' : 'async (dispatch via briefing transport)';
        $io->writeln('Mode: ' . $modeLabel);

        $io->writeln(sprintf(
            'Window: %s → %s (%d hours)',
            $windowStart->format('Y-m-d H:i'),
            $windowEnd->format('Y-m-d H:i'),
            $windowHours,
        ));
        $io->writeln(sprintf(
            'Eligibility floors: min_pr_count=%d, min_topic_relevance=%.2f',
            $minPrCount,
            $minRelevance,
        ));

        $topics = $this->topicRepository->findActiveWithUnprocessedPressReleasesSince(
            $windowStart,
            $minPrCount,
            $minRelevance,
        );

        if ($topics === []) {
            $io->success('No eligible topics in window — nothing to generate.');

            return Command::SUCCESS;
        }

        if ($limit > 0 && \count($topics) > $limit) {
            $io->writeln(sprintf('Found %d eligible topics; processing top %d (--limit).', \count($topics), $limit));
            $topics = \array_slice($topics, 0, $limit);
        } else {
            $io->writeln(sprintf('Found %d eligible topics; processing all.', \count($topics)));
        }

        // Async path: dispatch one message per eligible topic, return.
        // Per-topic eligibility/dedup/writer execution happens in the
        // GenerateTopicArticleHandler, not here.
        if (!$sync) {
            return $this->dispatchAsync($io, $topics, $windowStart, $windowEnd, $dryRun);
        }

        $stats = ['processed' => 0, 'generated' => 0, 'ineligible' => 0, 'writerNull' => 0];

        foreach ($topics as $topic) {
            $stats['processed']++;
            $topicLabel = sprintf('topic #%d "%s"', $topic->getId() ?? 0, $topic->getTitle() ?? 'n/a');

            $pressReleases = $this->pressReleaseRepository->findByTopicInWindow(
                $topic,
                $windowStart,
                $windowEnd,
            );

            $eligibility = $this->writerService->isEligibleForTopicWindow($topic, $pressReleases);
            if (!$eligibility['eligible']) {
                $io->writeln(sprintf(
                    '  %s — INELIGIBLE: %s',
                    $topicLabel,
                    implode('; ', $eligibility['reasons']),
                ));
                $stats['ineligible']++;
                continue;
            }

            $draft = $this->writerService->writeArticleFromTopicWindow(
                $topic,
                $windowStart,
                $windowEnd,
                $pressReleases,
            );

            if ($draft === null) {
                $io->writeln(sprintf(
                    '  %s — WRITER FAILED (Gemini error or invalid output, see logs)',
                    $topicLabel,
                ));
                $stats['writerNull']++;
                continue;
            }

            if ($dryRun) {
                $io->writeln(sprintf(
                    '  %s — DRY: would persist PR (title="%s", confidence=%.2f, sources=%d)',
                    $topicLabel,
                    mb_substr($draft->titleRo, 0, 60),
                    $draft->confidenceScore,
                    \count($draft->sourcePressReleaseIds),
                ));
                $stats['generated']++;
                continue;
            }

            $pr = $this->persistDraftAsPressRelease($draft, $topic);
            $io->writeln(sprintf(
                '  %s — OK: PR #%d created (confidence=%.2f, sources=%d)',
                $topicLabel,
                $pr->getId() ?? 0,
                $draft->confidenceScore,
                \count($draft->sourcePressReleaseIds),
            ));
            $stats['generated']++;
        }

        $io->section('Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Topics processed', (string) $stats['processed']],
                ['Drafts generated' . ($dryRun ? ' (dry-run)' : ''), (string) $stats['generated']],
                ['Ineligible', (string) $stats['ineligible']],
                ['Writer returned null', (string) $stats['writerNull']],
            ],
        );

        return Command::SUCCESS;
    }

    /**
     * Dispatch one GenerateTopicArticleMessage per eligible topic to the
     * shared `briefing` transport. Per-topic eligibility / dedup / writer
     * execution happens in GenerateTopicArticleHandler (T52.7).
     *
     * @param Topic[] $topics
     */
    private function dispatchAsync(
        SymfonyStyle $io,
        array $topics,
        \DateTimeImmutable $windowStart,
        \DateTimeImmutable $windowEnd,
        bool $dryRun,
    ): int {
        $dispatched = 0;
        foreach ($topics as $topic) {
            $topicId = $topic->getId();
            if ($topicId === null) {
                continue;
            }
            $topicLabel = sprintf('topic #%d "%s"', $topicId, $topic->getTitle() ?? 'n/a');

            if ($dryRun) {
                $io->writeln(sprintf('  %s — DRY: would dispatch GenerateTopicArticleMessage', $topicLabel));
                $dispatched++;
                continue;
            }

            $this->messageBus->dispatch(
                new GenerateTopicArticleMessage($topicId, $windowStart, $windowEnd),
            );
            $io->writeln(sprintf('  %s — DISPATCHED', $topicLabel));
            $dispatched++;
        }

        $io->section('Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Topics dispatched' . ($dryRun ? ' (dry-run)' : ''), (string) $dispatched],
            ],
        );
        $io->note('Per-topic outcome will be visible in the briefing worker logs (messenger:consume briefing).');

        return Command::SUCCESS;
    }

    /**
     * Persist a topic-window-generated draft as a pending PressRelease.
     * Mirrors the legacy cluster-path persistence shape but tags the
     * source name with the originating topic id (no schema change —
     * source_topic_id column not added in T52.6 scope).
     */
    private function persistDraftAsPressRelease(ArticleDraft $draft, Topic $topic): PressRelease
    {
        $pr = new PressRelease();
        $pr->setTitle(mb_substr($draft->titleRo, 0, 255));
        $pr->setLead($draft->leadRo);
        $pr->setContent($draft->contentRo);
        $pr->setStatus(PressReleaseStatus::PENDING);
        $pr->setSourceType(SourceType::AGGREGATOR);
        $pr->setSourceName(sprintf('AI:topic#%d', $topic->getId() ?? 0));
        $pr->setContentHash(hash('sha256', sprintf('ai_gen_topic_%d_%d', $topic->getId() ?? 0, time())));
        $pr->setCategorySlug('externe');
        $pr->setDetectedLanguage('ro');

        $pr->setAiConfidenceScore($draft->confidenceScore);
        $pr->setAiSourceCount(\count($draft->sourcePressReleaseIds));
        // Intentionally not setSourceClusterId — topic-window path does not
        // produce a cluster id. Topic traceability is in sourceName.

        $this->em->persist($pr);
        $this->em->flush();

        return $pr;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Legacy StoryCluster path (kept FUNCTIONAL for T52.10 quality
    //  comparison; removed in T52.12 per ADR-019 D6)
    // ────────────────────────────────────────────────────────────────────

    /**
     * NOTE: legacy StoryCluster generation path. Kept FUNCTIONAL through
     * the T52.10 quality-comparison checkpoint and removed wholesale in
     * T52.12 (ADR-019 D6). Deliberately not marked `@deprecated` here
     * because the public `execute()` dispatches into it whenever a
     * cluster-id argument is provided — internal call, not a public API.
     */
    private function executeLegacyClusterPath(
        SymfonyStyle $io,
        int $clusterId,
        bool $dryRun,
        bool $force,
        float $minScore,
    ): int {
        $io->title('Generate Article from StoryCluster (legacy)');

        $cluster = $this->clusterRepository->find($clusterId);
        if ($cluster === null) {
            $io->error(sprintf('Cluster #%d not found', $clusterId));

            return Command::FAILURE;
        }

        $io->writeln(sprintf('Cluster #%d: "%s"', $clusterId, $cluster->getPrimaryHeadline()));
        $io->writeln(sprintf(
            'Score: %.4f | Sources: %d | Articles: %d',
            $cluster->getImportanceScore(),
            $cluster->getSourceCount(),
            $cluster->getArticleCount(),
        ));

        if (!$force && $cluster->getImportanceScore() < $minScore) {
            $io->error(sprintf(
                'Cluster score %.4f is below minimum %.2f. Use --force to override.',
                $cluster->getImportanceScore(),
                $minScore,
            ));

            return Command::FAILURE;
        }

        if (!$force) {
            // @phpstan-ignore-next-line method.deprecated — legacy path intentional through T52.10 checkpoint
            $eligibility = $this->writerService->isEligibleForAiGeneration($cluster);
            if (!$eligibility['eligible']) {
                $io->error(sprintf(
                    'Content-depth gate failed: %s. Use --force to override.',
                    $eligibility['reason'],
                ));

                return Command::FAILURE;
            }
            $io->writeln(sprintf(
                'Content-depth: %d sources, avg %d chars — OK',
                $eligibility['sourceCount'],
                $eligibility['avgLength'],
            ));
        }

        $io->writeln('');
        $io->writeln('Calling Gemini CLI...');

        // @phpstan-ignore-next-line method.deprecated — legacy path intentional through T52.10 checkpoint
        $draft = $this->writerService->generateArticle($cluster);

        if ($draft === null) {
            $io->error('Article generation failed (Gemini error or invalid output)');

            return Command::FAILURE;
        }

        $io->section('Generated Draft');
        $io->writeln(sprintf('<info>Title:</info> %s', $draft->titleRo));
        $io->writeln(sprintf('<info>Lead:</info> %s', mb_substr($draft->leadRo, 0, 200)));
        $io->writeln(sprintf('<info>Content:</info> %d words', str_word_count($draft->contentRo)));
        $io->writeln(sprintf('<info>Meta:</info> %s', $draft->metaDescription));
        $io->writeln(sprintf('<info>Tags:</info> %s', implode(', ', $draft->suggestedTags)));
        $io->writeln(sprintf('<info>Confidence:</info> %.2f', $draft->confidenceScore));
        $io->writeln(sprintf('<info>Sources:</info> %d press releases', \count($draft->sourcePressReleaseIds)));

        if ($dryRun) {
            $io->note('DRY RUN — draft not persisted');

            return Command::SUCCESS;
        }

        $pr = new PressRelease();
        $pr->setTitle(mb_substr($draft->titleRo, 0, 255));
        $pr->setLead($draft->leadRo);
        $pr->setContent($draft->contentRo);
        $pr->setStatus(PressReleaseStatus::PENDING);
        $pr->setSourceType(SourceType::AGGREGATOR);
        $pr->setSourceName(sprintf('AI:cluster#%d', $cluster->getId() ?? 0));
        $pr->setContentHash(hash('sha256', 'ai_gen_' . ($cluster->getId() ?? 0) . '_' . time()));
        $pr->setCategorySlug('externe');
        $pr->setDetectedLanguage('ro');

        $pr->setAiConfidenceScore($draft->confidenceScore);
        $pr->setAiSourceCount(\count($draft->sourcePressReleaseIds));
        $pr->setSourceClusterId($draft->clusterId);

        $this->em->persist($pr);
        $this->em->flush();

        $io->success(sprintf(
            'PressRelease #%d created (status: pending). Approve via API to create Article.',
            $pr->getId() ?? 0,
        ));

        return Command::SUCCESS;
    }
}
