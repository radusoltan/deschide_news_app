<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\ArticleRepository;
use App\Service\TranslationPriorityDispatcher;
use App\Service\TranslationPriorityResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:translate:articles',
    description: 'Dispatch translation jobs for articles with priority routing (Gemini CLI)',
)]
class TranslateArticlesCommand extends Command
{
    public function __construct(
        private readonly TranslationPriorityDispatcher $dispatcher,
        private readonly TranslationPriorityResolver $priorityResolver,
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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locales = explode(',', $input->getOption('locales'));
        $force = $input->getOption('force');
        $ids = $input->getArgument('ids');

        if (!empty($ids)) {
            $articleIds = array_map(fn ($id) => (int) $id, $ids);
        } else {
            $limit = (int) $input->getOption('limit');
            $articleIds = $this->findUntranslatedArticleIds($limit, $force);
        }

        if (empty($articleIds)) {
            $io->success('No articles to translate.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('Translating %d articles → %s', \count($articleIds), implode(', ', $locales)));

        try {
        foreach ($articleIds as $articleId) {
            $article = $this->articleRepository->find($articleId);
            if (!$article) {
                $io->warning("Article #{$articleId} not found, skipping.");

                continue;
            }

            $priority = $this->priorityResolver->resolve($article);

            $io->text(sprintf(
                '  → #%d [%s]: %s',
                $articleId,
                strtoupper($priority->label()),
                mb_substr($article->getTitle() ?? '', 0, 60),
            ));

            $this->dispatcher->dispatch($article, $locales, forceRetranslate: $force);
            $io->text('    📨 Dispatched to ' . TranslationPriorityDispatcher::queueName($priority));
        }

        $io->note('Run the priority worker to process:');
        $io->text('  messenger:consume translations_critical translations_urgent translations_high translations -vv');

        $io->success(sprintf('Done. %d translation jobs dispatched.', \count($articleIds)));

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Translation dispatch failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
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
