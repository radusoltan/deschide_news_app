<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\TranslationPriority;
use App\Repository\ArticleRepository;
use App\Service\TranslationPriorityDispatcher;
use App\Service\TranslationPriorityResolver;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:translate:pending',
    description: 'Dispatch pending articles for translation in priority order (cron batch)',
)]
class TranslatePendingArticlesCommand extends Command
{
    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly TranslationPriorityResolver $priorityResolver,
        private readonly TranslationPriorityDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max articles to dispatch per run', '10')
            ->addOption('priority', 'p', InputOption::VALUE_REQUIRED, 'Only process specific priority (critical,urgent,high,normal)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be dispatched without dispatching');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $dryRun = $input->getOption('dry-run');
        $priorityFilter = $input->getOption('priority');

        try {
        // Find pending articles (status=published, translationStatus=pending or null)
        $pendingArticles = $this->findPendingArticles($limit * 3); // Fetch extra for priority sorting

        if (empty($pendingArticles)) {
            $io->info('No pending articles to translate.');

            return Command::SUCCESS;
        }

        // Resolve priority for each article and sort
        $prioritized = [];
        foreach ($pendingArticles as $article) {
            $priority = $this->priorityResolver->resolve($article);

            // Filter by specific priority if requested
            if ($priorityFilter !== null && $priority->label() !== $priorityFilter) {
                continue;
            }

            $prioritized[] = ['article' => $article, 'priority' => $priority];
        }

        // Sort by priority value (0=critical first, 3=normal last)
        usort($prioritized, fn ($a, $b) => $a['priority']->value <=> $b['priority']->value);

        // Limit to requested count
        $prioritized = \array_slice($prioritized, 0, $limit);

        if (empty($prioritized)) {
            $io->info('No articles matching criteria.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('Dispatching %d pending translations', \count($prioritized)));

        $dispatched = 0;
        foreach ($prioritized as $item) {
            $article = $item['article'];
            $priority = $item['priority'];

            $io->text(sprintf(
                '  [%s] #%d: %s',
                strtoupper($priority->label()),
                $article->getId(),
                mb_substr($article->getTitle() ?? '', 0, 70),
            ));

            if (!$dryRun) {
                $this->dispatcher->dispatch($article, ['ru', 'en']);
                ++$dispatched;
            }
        }

        if ($dryRun) {
            $io->note('Dry run — nothing dispatched.');
        } else {
            $io->success(sprintf('%d translation jobs dispatched.', $dispatched));
        }

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Translation dispatch failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return \App\Entity\Article[]
     */
    private function findPendingArticles(int $limit): array
    {
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->where('a.status = :status')
            ->andWhere('a.translationStatus IS NULL OR a.translationStatus = :pending OR a.translationStatus = :failed')
            ->setParameter('status', 'published')
            ->setParameter('pending', 'pending')
            ->setParameter('failed', 'failed')
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }
}
