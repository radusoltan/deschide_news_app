<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Repository\ArticleRepository;
use App\Service\SeoBatchPromptBuilder;
use App\Service\SeoResultProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:seo:backfill',
    description: 'Batch SEO optimization for all articles using Gemini AI',
)]
final class SeoBackfillCommand extends Command
{
    private const GEMINI_TIMEOUT = 180; // higher for batch

    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly SeoBatchPromptBuilder $batchPromptBuilder,
        private readonly SeoResultProcessor $resultProcessor,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $geminiCliPath,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Articles per Gemini call', '10')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing metaTitle/metaDescription')
            ->addOption('no-tags', null, InputOption::VALUE_NONE, 'Skip tag suggestions')
            ->addOption('no-meta', null, InputOption::VALUE_NONE, 'Skip metaTitle/metaDescription generation')
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Start from article N (for resuming)', '0')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max articles to process (0 = all)', '0')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be processed without calling Gemini');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $batchSize = (int) $input->getOption('batch-size');
        $force = $input->getOption('force');
        $noTags = $input->getOption('no-tags');
        $noMeta = $input->getOption('no-meta');
        $offset = (int) $input->getOption('offset');
        $limit = (int) $input->getOption('limit');
        $dryRun = $input->getOption('dry-run');

        if ($noTags && $noMeta) {
            $io->error('Cannot use both --no-tags and --no-meta.');

            return Command::FAILURE;
        }

        if ($batchSize < 1 || $batchSize > 20) {
            $io->error('Batch size must be between 1 and 20.');

            return Command::FAILURE;
        }

        $options = [
            'generateMeta' => !$noMeta,
            'suggestTags' => !$noTags,
            'force' => $force,
        ];

        $io->title('SEO Backfill — Batch Processing');

        // Query eligible articles
        $articles = $this->getEligibleArticles($force, $offset, $limit);
        $total = \count($articles);

        if ($total === 0) {
            $io->success('No articles need SEO optimization.');

            return Command::SUCCESS;
        }

        $io->text(\sprintf(
            'Found <info>%d</info> articles to process (offset=%d, limit=%s, force=%s)',
            $total,
            $offset,
            $limit > 0 ? (string) $limit : 'all',
            $force ? 'yes' : 'no',
        ));

        if ($dryRun) {
            $io->section('Dry Run — Articles to process');
            $rows = [];
            foreach (\array_slice($articles, 0, 30) as $article) {
                $rows[] = [
                    $article->getId(),
                    mb_substr($article->getTitle() ?? '', 0, 60),
                    $article->getMetaTitle() !== null ? 'YES' : 'no',
                    $article->getTags()->count(),
                ];
            }
            $io->table(['ID', 'Title (first 60)', 'Has Meta', 'Tags'], $rows);

            if ($total > 30) {
                $io->text(\sprintf('... and %d more articles', $total - 30));
            }

            $batchCount = (int) ceil($total / $batchSize);
            $io->text(\sprintf('Would process in <info>%d</info> batches of %d', $batchCount, $batchSize));

            return Command::SUCCESS;
        }

        // Process in batches
        $batches = array_chunk($articles, $batchSize);
        $batchCount = \count($batches);

        $io->text(\sprintf('Processing in <info>%d</info> batches of %d', $batchCount, $batchSize));
        $io->newLine();

        $progressBar = new ProgressBar($output, $total);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% — %message%');
        $progressBar->setMessage('Starting...');
        $progressBar->start();

        $stats = [
            'processed' => 0,
            'metaSet' => 0,
            'tagsLinked' => 0,
            'tagsCreated' => 0,
            'batchErrors' => 0,
            'articleErrors' => 0,
            'skipped' => 0,
        ];

        $startTime = microtime(true);

        foreach ($batches as $batchIndex => $batch) {
            $batchNum = $batchIndex + 1;
            $progressBar->setMessage(\sprintf('Batch %d/%d...', $batchNum, $batchCount));

            try {
                $batchResult = $this->processBatch($batch, $options);

                $stats['processed'] += $batchResult['processed'];
                $stats['metaSet'] += $batchResult['metaSet'];
                $stats['tagsLinked'] += $batchResult['tagsLinked'];
                $stats['tagsCreated'] += $batchResult['tagsCreated'];
                $stats['articleErrors'] += $batchResult['articleErrors'];

                // Clear EntityManager to prevent memory bloat
                $this->em->clear();
            } catch (ProcessTimedOutException) {
                $stats['batchErrors']++;
                $this->logger->error('SeoBackfill: batch timeout', [
                    'batch' => $batchNum,
                    'articleIds' => array_map(fn (Article $a) => $a->getId(), $batch),
                ]);
                $progressBar->setMessage(\sprintf('Batch %d TIMEOUT, continuing...', $batchNum));
            } catch (\Throwable $e) {
                $stats['batchErrors']++;
                $this->logger->error('SeoBackfill: batch failed', [
                    'batch' => $batchNum,
                    'error' => $e->getMessage(),
                ]);
                $progressBar->setMessage(\sprintf('Batch %d ERROR, continuing...', $batchNum));
            }

            $progressBar->advance(\count($batch));
        }

        $progressBar->setMessage('Done!');
        $progressBar->finish();
        $io->newLine(2);

        $duration = round(microtime(true) - $startTime, 1);

        // Summary
        $io->section('Summary');
        $io->definitionList(
            ['Articles processed' => $stats['processed']],
            ['Meta titles set' => $stats['metaSet']],
            ['Tags linked (existing)' => $stats['tagsLinked']],
            ['Tags created (new)' => $stats['tagsCreated']],
            ['Batch errors' => $stats['batchErrors']],
            ['Article errors' => $stats['articleErrors']],
            ['Duration' => \sprintf('%dm %ds', (int) ($duration / 60), (int) $duration % 60)],
            ['Gemini calls' => $batchCount - $stats['batchErrors']],
        );

        if ($stats['batchErrors'] > 0) {
            $io->warning(\sprintf('%d batch(es) failed. Check logs for details.', $stats['batchErrors']));
        }

        $io->success(\sprintf('Backfill completed: %d articles in %dm %ds', $stats['processed'], (int) ($duration / 60), (int) $duration % 60));

        return Command::SUCCESS;
    }

    /**
     * @return Article[]
     */
    private function getEligibleArticles(bool $force, int $offset, int $limit): array
    {
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->where('a.title IS NOT NULL')
            ->andWhere("a.title != ''")
            ->andWhere("(a.lead IS NOT NULL AND a.lead != '') OR (a.content IS NOT NULL AND a.content != '')")
            ->orderBy('a.id', 'ASC');

        if (!$force) {
            // Skip articles that already have both meta fields set
            $qb->andWhere('a.metaTitle IS NULL OR a.metaDescription IS NULL');
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
     * Process a single batch of articles via Gemini.
     *
     * @param Article[] $articles
     * @param array{generateMeta: bool, suggestTags: bool, force: bool} $options
     *
     * @return array{processed: int, metaSet: int, tagsLinked: int, tagsCreated: int, articleErrors: int}
     */
    private function processBatch(array $articles, array $options): array
    {
        $result = ['processed' => 0, 'metaSet' => 0, 'tagsLinked' => 0, 'tagsCreated' => 0, 'articleErrors' => 0];

        $prompt = $this->batchPromptBuilder->build($articles, $options);
        $output = $this->runGemini($prompt);
        $dataArray = $this->parseBatchResponse($output);

        // Index articles by ID for quick lookup
        $articleMap = [];
        foreach ($articles as $article) {
            $articleMap[$article->getId()] = $article;
        }

        foreach ($dataArray as $itemData) {
            if (!\is_array($itemData) || !isset($itemData['articleId'])) {
                $result['articleErrors']++;

                continue;
            }

            $articleId = (int) $itemData['articleId'];
            $article = $articleMap[$articleId] ?? null;

            if ($article === null) {
                $this->logger->warning('SeoBackfill: articleId from Gemini not in batch', [
                    'articleId' => $articleId,
                ]);
                $result['articleErrors']++;

                continue;
            }

            try {
                $seoResult = $this->resultProcessor->process($article, $itemData, $options);

                $result['processed']++;
                if ($seoResult['metaTitle'] !== null || $seoResult['metaDescription'] !== null) {
                    $result['metaSet']++;
                }
                $result['tagsLinked'] += \count($seoResult['tagsExisting']);
                $result['tagsCreated'] += \count($seoResult['tagsAdded']);
            } catch (\Throwable $e) {
                $result['articleErrors']++;
                $this->logger->error('SeoBackfill: article processing failed', [
                    'articleId' => $articleId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    private function runGemini(string $prompt): string
    {
        $process = new Process(
            command: [
                $this->geminiCliPath,
                '-p', 'Analyze articles and generate SEO metadata for each. Return JSON array.',
                '-o', 'json',
            ],
            cwd: $this->projectDir,
            env: ['HOME' => '/home/radu', 'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin'],
            timeout: self::GEMINI_TIMEOUT,
        );

        $process->setInput($prompt);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(
                'Gemini CLI failed (exit ' . $process->getExitCode() . '): '
                . $process->getErrorOutput()
            );
        }

        $output = trim($process->getOutput());

        if (empty($output)) {
            throw new \RuntimeException('Gemini CLI returned empty output');
        }

        return $output;
    }

    /**
     * Parse Gemini batch response — expects a JSON array.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseBatchResponse(string $rawOutput): array
    {
        $cleaned = trim($rawOutput);

        // Strip control characters
        $map = [];
        for ($i = 0; $i <= 0x1F; ++$i) {
            $map[\chr($i)] = ' ';
        }
        $map[\chr(0x7F)] = ' ';
        $cleaned = strtr($cleaned, $map);

        $decoded = json_decode($cleaned, true, 512, \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new \RuntimeException('Gemini batch response did not decode to an array');
        }

        // Handle Gemini CLI envelope: { session_id, response, stats }
        if (isset($decoded['response']) && \is_string($decoded['response'])) {
            $responseStr = $decoded['response'];

            // Strip markdown fences
            $responseStr = preg_replace('/^```(?:json)?\s*/m', '', $responseStr);
            $responseStr = preg_replace('/\s*```\s*$/m', '', $responseStr ?? $decoded['response']);
            $responseStr = trim($responseStr ?? $decoded['response']);

            // Extract JSON array: find first '[' to last ']'
            $jsonStart = strpos($responseStr, '[');
            $jsonEnd = strrpos($responseStr, ']');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $responseStr = substr($responseStr, $jsonStart, $jsonEnd - $jsonStart + 1);
            }

            $responseStr = strtr($responseStr, $map);

            $inner = json_decode($responseStr, true, 512, \JSON_THROW_ON_ERROR);

            if (\is_array($inner)) {
                // Could be an indexed array of items
                return $this->normalizeResponse($inner);
            }
        }

        // Direct JSON array (no envelope)
        return $this->normalizeResponse($decoded);
    }

    /**
     * Normalize response: ensure it's a list of article SEO results.
     *
     * @param array<mixed> $data
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeResponse(array $data): array
    {
        // Already an indexed array of objects
        if (isset($data[0]) && \is_array($data[0])) {
            return $data;
        }

        // Single object (batch of 1) — wrap in array
        if (isset($data['articleId'])) {
            return [$data];
        }

        throw new \RuntimeException('Unexpected Gemini batch response format');
    }
}
