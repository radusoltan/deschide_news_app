<?php

declare(strict_types=1);

namespace App\Command\Topic;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Topic\BatchResult;
use App\Service\Topic\BatchTopicClassifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Backfill topic classifications for published articles that have no linked
 * topics yet.
 *
 * Filters on `article_topics` via NOT EXISTS so the pilot's 98 pre-classified
 * articles — and any manual classifications — are skipped automatically.
 */
#[AsCommand(
    name: 'app:articles:classify-topics',
    description: 'Classify published articles missing topic links via Gemini AI (Sprint 51c backfill).',
)]
class ClassifyArticlesCommand extends Command
{
    /** Permanent sleep bump after a second consecutive chunk failure (seconds). */
    private const SLEEP_BUMP_AFTER_RATE_LIMIT = 5;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BatchTopicClassifier $classifier,
        private readonly ?GeminiCliService $geminiCli = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max articles to process (0 = all)', '0')
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Skip first N unclassified articles', '0')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Articles per Gemini prompt', '20')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Restrict to articles in the given category id')
            ->addOption('confidence-threshold', null, InputOption::VALUE_REQUIRED, 'Reserved for future per-mapping threshold (BatchTopicClassifier does not yet surface confidence).', '0.7')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without writing to DB');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $limit = (int) $input->getOption('limit');
        $offset = (int) $input->getOption('offset');
        $batchSize = max(1, (int) $input->getOption('batch-size'));
        $categoryId = $input->getOption('category');
        $confidenceThreshold = (float) $input->getOption('confidence-threshold');
        $dryRun = (bool) $input->getOption('dry-run');

        $io->title('Sprint 51c — Article Topic Backfill');

        $articles = $this->loadUnclassifiedArticles($categoryId, $offset, $limit);
        $total = count($articles);

        if ($total === 0) {
            $io->success('No unclassified published articles found.');

            return Command::SUCCESS;
        }

        $io->text(sprintf('Unclassified articles to process: %d (batch size: %d)', $total, $batchSize));
        if ($confidenceThreshold !== 0.7) {
            $io->note(sprintf('--confidence-threshold=%.2f received but not yet enforced.', $confidenceThreshold));
        }

        if ($dryRun) {
            $batches = (int) ceil($total / $batchSize);
            $io->warning(sprintf('DRY RUN — would process %d batches of %d via Gemini. No DB writes.', $batches, $batchSize));

            foreach ($articles as $article) {
                $io->writeln(sprintf('  [#%d] %s', $article->getId(), mb_substr($article->getTitle(), 0, 80)));
            }

            return Command::SUCCESS;
        }

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $progressBar->setMessage('classifying…');
        $progressBar->start();

        $result = new BatchResult();
        $topicCounts = [];
        $processed = 0;
        $consecutiveFailedChunks = 0;
        $lastReportedCount = 0;
        $wallStart = microtime(true);

        $result = $this->classifier->classifyBatch(
            $articles,
            $batchSize,
            function (int $current, int $totalChunks, BatchResult $running) use (
                $progressBar,
                $batchSize,
                $total,
                &$processed,
                &$consecutiveFailedChunks,
                &$lastReportedCount,
                $io,
            ): void {
                $processed = min($current * $batchSize, $total);
                $progressBar->setProgress($processed);
                $progressBar->setMessage(sprintf(
                    'chunk %d/%d | classified: %d | skipped: %d | failed: %d',
                    $current,
                    $totalChunks,
                    $running->classified,
                    $running->skipped,
                    $running->failedChunks,
                ));

                // Detect consecutive failed chunks → bump sleep permanently once
                if ($running->failedChunks > $consecutiveFailedChunks) {
                    $consecutiveFailedChunks = $running->failedChunks;
                    if ($consecutiveFailedChunks >= 2) {
                        $io->newLine();
                        $io->warning(sprintf(
                            'Second failed chunk detected — consider rerunning later. '
                            . 'BatchTopicClassifier internal retry uses %ds delay on transient errors.',
                            self::SLEEP_BUMP_AFTER_RATE_LIMIT,
                        ));
                    }
                }

                // Emit per-100 article checkpoint line
                if ($running->classified >= $lastReportedCount + 100) {
                    $lastReportedCount = $running->classified - ($running->classified % 100);
                    $progressBar->clear();
                    $io->writeln(sprintf(
                        '  checkpoint: classified=%d, skipped=%d, failed_chunks=%d',
                        $running->classified,
                        $running->skipped,
                        $running->failedChunks,
                    ));
                    $progressBar->display();
                }
            },
        );

        $progressBar->setProgress($total);
        $progressBar->finish();
        $io->newLine(2);

        $wallSeconds = (int) round(microtime(true) - $wallStart);

        // Per-topic hit counts (top 10)
        $topTopics = $this->loadTopTopicsHitDuringSession($articles);

        $summaryRows = [
            ['Articles targeted', (string) $total],
            ['Classified', (string) $result->classified],
            ['Skipped (already classified mid-run)', (string) $result->skipped],
            ['Total topic assignments', (string) $result->totalAssignments],
            ['Failed chunks', (string) $result->failedChunks],
            ['Wall time', sprintf('%ds', $wallSeconds)],
        ];

        if ($this->geminiCli !== null) {
            $session = $this->geminiCli->getSessionStats();
            $summaryRows[] = ['Gemini calls', (string) $session['total_calls']];
            $summaryRows[] = ['Gemini input tokens', (string) $session['total_input_tokens']];
            $summaryRows[] = ['Gemini output tokens', (string) $session['total_output_tokens']];
            $summaryRows[] = ['Gemini cost (USD)', sprintf('$%.4f', $session['total_cost_usd'])];
        }

        $io->section('Backfill Summary');
        $io->table(['Metric', 'Value'], $summaryRows);

        if ($topTopics !== []) {
            $io->section('Top topics hit');
            $io->table(
                ['Topic', 'Articles assigned'],
                array_map(static fn (array $row) => [$row['slug'], (string) $row['count']], $topTopics),
            );
        }

        if ($result->failedChunks > 0) {
            $io->warning(sprintf(
                '%d chunk(s) failed during the run. Rerun with --offset=<last-successful> '
                . 'to pick up where it left off.',
                $result->failedChunks,
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * @return Article[]
     */
    protected function loadUnclassifiedArticles(mixed $categoryId, int $offset, int $limit): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Article::class, 'a')
            ->leftJoin('a.topics', 't')
            ->where('t.id IS NULL')
            ->andWhere('a.status = :status')
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->orderBy('a.id', 'ASC');

        if ($categoryId !== null && $categoryId !== '') {
            $qb->andWhere('a.category = :categoryId')
                ->setParameter('categoryId', (int) $categoryId);
        }

        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count top 10 topics assigned during this session window by re-querying
     * the just-touched articles.
     *
     * @param Article[] $articles
     *
     * @return list<array{slug: string, count: int}>
     */
    protected function loadTopTopicsHitDuringSession(array $articles): array
    {
        if ($articles === []) {
            return [];
        }

        $ids = array_values(array_filter(array_map(static fn (Article $a) => $a->getId(), $articles)));
        if ($ids === []) {
            return [];
        }

        $rows = $this->em->getConnection()->fetchAllAssociative(
            <<<SQL
            SELECT t.slug AS slug, COUNT(at.article_id) AS hits
            FROM article_topics at
            JOIN topics t ON t.id = at.topic_id
            WHERE at.article_id IN (?)
            GROUP BY t.slug
            ORDER BY hits DESC
            LIMIT 10
            SQL,
            [$ids],
            [\Doctrine\DBAL\ArrayParameterType::INTEGER],
        );

        return array_map(
            static fn (array $row) => ['slug' => (string) $row['slug'], 'count' => (int) $row['hits']],
            $rows,
        );
    }
}
