<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Repository\TopicRepository;
use App\Service\Topic\BatchTopicClassifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:topics:batch-classify',
    description: 'Classify articles into topics via AI batch (Gemini).',
)]
class BatchClassifyTopicsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BatchTopicClassifier $batchClassifier,
        private readonly TopicRepository $topicRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Articles per Gemini prompt', '20')
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Skip first N unclassified articles', '0')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max articles to process (0 = all)', '0')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Only process articles in this category')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without saving');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');
        $offset = (int) $input->getOption('offset');
        $limit = (int) $input->getOption('limit');
        $categoryFilter = $input->getOption('category');
        $dryRun = $input->getOption('dry-run');

        $io->title('Topic Batch Classification — AI-only (Sprint 51c)');

        try {
            // Show current state
            $totalArticles = $this->countArticles();
            $classified = $this->countClassified();
            $io->text(sprintf('Articles: %d total, %d classified, %d unclassified',
                $totalArticles, $classified, $totalArticles - $classified
            ));
            $io->text(sprintf('Topics: %d active', count($this->topicRepository->findBy(['isActive' => true]))));
            $io->newLine();

            $io->section('AI Batch Classification (Gemini)');
            $aiCount = $this->runAiBatch($io, $batchSize, $categoryFilter, $offset, $limit, $dryRun);

            // REPORT
            $io->newLine();
            $io->section('Final Report');
            $nowClassified = $dryRun ? $classified : $this->countClassified();
            $coverage = $totalArticles > 0 ? round($nowClassified / $totalArticles * 100, 1) : 0;

            $io->table(
                ['Metric', 'Value'],
                [
                    ['AI classified', (string) $aiCount],
                    ['Total classified now', (string) $nowClassified],
                    ['Coverage', $coverage . '%'],
                ]
            );

            if ($dryRun) {
                $io->warning('DRY RUN — no changes were saved');
            }

            if ($coverage >= 90) {
                $io->success(sprintf('Target reached! Coverage: %.1f%%', $coverage));
            } else {
                $io->note(sprintf('Coverage: %.1f%% (target: 90%%)', $coverage));
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Topic classification failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function runAiBatch(SymfonyStyle $io, int $batchSize, ?string $categoryFilter, int $offset, int $limit, bool $dryRun): int
    {
        $articles = $this->getUnclassifiedArticles($categoryFilter, $offset, $limit);
        $io->text(sprintf('Found %d unclassified articles for AI phase', count($articles)));

        if (count($articles) === 0) {
            $io->text('No articles remaining for AI classification');

            return 0;
        }

        if ($dryRun) {
            $batches = (int) ceil(count($articles) / $batchSize);
            $io->text(sprintf('Would process %d articles in %d batches of %d (dry run)', count($articles), $batches, $batchSize));

            return 0;
        }

        $result = $this->batchClassifier->classifyBatch(
            $articles,
            $batchSize,
            function (int $current, int $total, $batchResult) use ($io) {
                $io->text(sprintf(
                    '  Batch %d/%d — classified: %d, skipped: %d, failed chunks: %d',
                    $current, $total,
                    $batchResult->classified,
                    $batchResult->skipped,
                    $batchResult->failedChunks
                ));
            }
        );

        $io->text(sprintf(
            'AI phase complete: %d classified, %d skipped, %d total assignments, %d failed chunks',
            $result->classified, $result->skipped, $result->totalAssignments, $result->failedChunks
        ));

        return $result->classified;
    }

    /**
     * @return Article[]
     */
    private function getUnclassifiedArticles(?string $categoryFilter, int $offset, int $limit): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a')
            ->from(Article::class, 'a')
            ->leftJoin('a.topics', 't')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->where('t.id IS NULL')
            ->orderBy('a.id', 'ASC');

        if ($categoryFilter !== null) {
            $qb->andWhere('c.title = :category')
                ->setParameter('category', $categoryFilter);
        }

        if ($offset > 0) {
            $qb->setFirstResult($offset);
        }

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    private function countArticles(): int
    {
        return (int) $this->em->createQuery('SELECT COUNT(a.id) FROM App\Entity\Article a')
            ->getSingleScalarResult();
    }

    private function countClassified(): int
    {
        return (int) $this->em->getConnection()
            ->executeQuery('SELECT COUNT(DISTINCT article_id) FROM article_topics')
            ->fetchOne();
    }
}
