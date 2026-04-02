<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Service\Translation\TranslationEvaluatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:translation:evaluate',
    description: 'Evaluează și optimizează calitatea traducerilor',
)]
final class TranslationEvaluateCommand extends Command
{
    public function __construct(
        private readonly TranslationEvaluatorService $evaluator,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('article-id', null, InputOption::VALUE_REQUIRED, 'Evaluate a specific article')
            ->addOption('lang', 'l', InputOption::VALUE_REQUIRED, 'Target language (en, ru)', 'en')
            ->addOption('batch', 'b', InputOption::VALUE_REQUIRED, 'Batch size (articles with translations)', '20')
            ->addOption('threshold', 't', InputOption::VALUE_REQUIRED, 'Quality score threshold', '0.85')
            ->addOption('max-iterations', null, InputOption::VALUE_REQUIRED, 'Max optimization iterations', '2')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only evaluate, do not optimize')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $articleId = $input->getOption('article-id');
        $lang = $input->getOption('lang');
        $batchSize = (int) $input->getOption('batch');
        $threshold = (float) $input->getOption('threshold');
        $maxIterations = (int) $input->getOption('max-iterations');
        $dryRun = $input->getOption('dry-run');

        if (!\in_array($lang, ['en', 'ru'], true)) {
            $io->error('Language must be "en" or "ru"');

            return Command::INVALID;
        }

        $io->title('Evaluare calitate traduceri');
        $io->text(\sprintf('Limba: %s | Prag: %.2f | Max iterații: %d%s',
            strtoupper($lang), $threshold, $maxIterations, $dryRun ? ' [DRY RUN]' : ''));

        $articleIds = $this->resolveArticleIds($articleId, $lang, $batchSize);

        if ($articleIds === []) {
            $io->warning('Nu s-au găsit articole cu traduceri pentru evaluare.');

            return Command::SUCCESS;
        }

        $io->text(\sprintf('Evaluare %d articol(e)...', \count($articleIds)));
        $io->newLine();

        $rows = [];
        $totalInitial = 0.0;
        $totalFinal = 0.0;

        foreach ($articleIds as $id) {
            try {
                if ($dryRun) {
                    $result = $this->evaluator->evaluateAndOptimize($id, $lang, 0, $threshold);
                } else {
                    $result = $this->evaluator->evaluateAndOptimize($id, $lang, $maxIterations, $threshold);
                }

                $rows[] = [
                    $result->articleId,
                    strtoupper($result->targetLang),
                    \sprintf('%.2f', $result->initialScore),
                    \sprintf('%.2f', $result->finalScore),
                    $result->iterations,
                    $result->finalStatus === 'complete' ? '<fg=green>complete</>' : '<fg=yellow>needs_review</>',
                ];

                $totalInitial += $result->initialScore;
                $totalFinal += $result->finalScore;
            } catch (\Throwable $e) {
                $rows[] = [$id, strtoupper($lang), '-', '-', 0, '<fg=red>error</>'];
                $io->warning(\sprintf('Articol #%d: %s', $id, $e->getMessage()));
            }

            // Clear entity manager to avoid memory issues in batch
            $this->em->clear();
        }

        $io->table(
            ['Articol', 'Limba', 'Scor inițial', 'Scor final', 'Iterații', 'Status'],
            $rows,
        );

        $count = \count($articleIds);
        if ($count > 0) {
            $io->text(\sprintf(
                'Scor mediu: %.2f → %.2f (%d articole)',
                $totalInitial / $count,
                $totalFinal / $count,
                $count,
            ));
        }

        $io->success('Evaluare completă.');

        return Command::SUCCESS;
    }

    /**
     * @return int[]
     */
    private function resolveArticleIds(?string $articleId, string $lang, int $batchSize): array
    {
        if ($articleId !== null) {
            return [(int) $articleId];
        }

        // Find articles that have translations in the target language
        $conn = $this->em->getConnection();
        $sql = <<<'SQL'
            SELECT DISTINCT CAST(t.foreign_key AS INTEGER) AS article_id
            FROM ext_translations t
            WHERE t.object_class = :class
              AND t.locale = :locale
              AND t.field = 'content'
              AND t.content IS NOT NULL
              AND t.content != ''
            ORDER BY article_id DESC
            LIMIT :limit
            SQL;

        $result = $conn->executeQuery($sql, [
            'class' => Article::class,
            'locale' => $lang,
            'limit' => $batchSize,
        ], [
            'limit' => \Doctrine\DBAL\ParameterType::INTEGER,
        ]);

        return array_map(fn ($row) => (int) $row['article_id'], $result->fetchAllAssociative());
    }
}
