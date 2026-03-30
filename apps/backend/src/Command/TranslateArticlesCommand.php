<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\TranslateArticleMessage;
use App\Repository\ArticleRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:translate:articles',
    description: 'Dispatch translation jobs for articles (Gemini CLI)',
)]
class TranslateArticlesCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ArticleRepository $articleRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('ids', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Article IDs to translate (space-separated)')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Translate N untranslated articles', '5')
            ->addOption('locales', null, InputOption::VALUE_REQUIRED, 'Target locales (comma-separated)', 'ru,en')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force retranslation even if already translated')
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Run synchronously (invoke handler directly, no queue)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locales = explode(',', $input->getOption('locales'));
        $force = $input->getOption('force');
        $sync = $input->getOption('sync');
        $ids = $input->getArgument('ids');

        if (!empty($ids)) {
            $articles = array_map(fn($id) => (int) $id, $ids);
        } else {
            $limit = (int) $input->getOption('limit');
            // Find untranslated published articles
            $articles = $this->findUntranslatedArticleIds($limit, $force);
        }

        if (empty($articles)) {
            $io->success('No articles to translate.');
            return Command::SUCCESS;
        }

        $io->title(sprintf('Translating %d articles → %s', count($articles), implode(', ', $locales)));

        foreach ($articles as $articleId) {
            $article = $this->articleRepository->find($articleId);
            if (!$article) {
                $io->warning("Article #{$articleId} not found, skipping.");
                continue;
            }

            $io->text(sprintf('  → #%d: %s', $articleId, mb_substr($article->getTitle() ?? '', 0, 60)));

            $message = new TranslateArticleMessage(
                articleId: $articleId,
                locales: $locales,
                forceRetranslate: $force,
            );

            if ($sync) {
                // Direct invocation — useful for testing
                $io->text('    ⏳ Running synchronously...');
                try {
                    $this->messageBus->dispatch($message);
                    $io->text('    ✅ Dispatched (sync)');
                } catch (\Throwable $e) {
                    $io->error("    ❌ Failed: " . $e->getMessage());
                }
            } else {
                $this->messageBus->dispatch($message);
                $io->text('    📨 Dispatched to queue');
            }
        }

        if (!$sync) {
            $io->note('Messages dispatched to queue. Run the worker to process them:');
            $io->text('  php bin/console messenger:consume translations -vv --limit=5');
        }

        $io->success(sprintf('Done. %d translation jobs dispatched.', count($articles)));

        return Command::SUCCESS;
    }

    /**
     * @return int[]
     */
    private function findUntranslatedArticleIds(int $limit, bool $force): array
    {
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->select('a.id')
            ->where('a.status = :status')
            ->setParameter('status', 'published')
            ->orderBy('a.id', 'DESC')
            ->setMaxResults($limit);

        if (!$force) {
            $qb->andWhere('a.translationStatus IS NULL OR a.translationStatus = :pending')
                ->setParameter('pending', 'pending');
        }

        return array_column($qb->getQuery()->getScalarResult(), 'id');
    }
}
