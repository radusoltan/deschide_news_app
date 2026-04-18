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
use App\Repository\TopicRepository;
use App\Service\Editorial\ArticleWriterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Generate AI articles via the Topic + Window path (ADR-019 D2).
 *
 * Async by default (dispatches GenerateTopicArticleMessage per eligible topic
 * to the `briefing` transport); --sync forces in-process synchronous generation
 * for local debugging.
 */
#[AsCommand(
    name: 'app:generate-article',
    description: 'Generate AI articles from eligible topics via the topic-window path.',
)]
class GenerateArticleCommand extends Command
{
    public function __construct(
        private readonly ArticleWriterService $writerService,
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview only, do not persist any PressRelease')
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Maximum number of topics to process (0 = unlimited)',
                '0',
            )
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Force synchronous in-process generation (default is async dispatch via GenerateTopicArticleMessage)')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Async dispatch via GenerateTopicArticleMessage (default since T52.7 — flag kept for clarity; no-op if --sync is also set)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        return $this->executeTopicWindowPath(
            $io,
            (bool) $input->getOption('dry-run'),
            (int) $input->getOption('limit'),
            (bool) $input->getOption('sync'),
        );
    }

    private function executeTopicWindowPath(SymfonyStyle $io, bool $dryRun, int $limit, bool $sync): int
    {
        $io->title('Generate Articles — Topic + Window (ADR-019 D2)');

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

        $this->em->persist($pr);
        $this->em->flush();

        return $pr;
    }
}
