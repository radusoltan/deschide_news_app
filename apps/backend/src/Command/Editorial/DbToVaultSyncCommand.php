<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use App\Dto\Editorial\VaultSyncResult;
use App\Entity\Article;
use App\Service\Editorial\DbToVaultSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:vault:sync-from-db',
    description: 'Sincronizează articolele din DB în vault (DB→Vault unidirecțional)',
)]
final class DbToVaultSyncCommand extends Command
{
    public function __construct(
        private readonly DbToVaultSyncService $syncService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Sincronizează toate articolele publicate')
            ->addOption('article-id', null, InputOption::VALUE_REQUIRED, 'Sincronizează un singur articol după ID')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Articole modificate de la data (YYYY-MM-DD)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Număr maxim de articole per batch', '100')
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Offset pentru paginare', '0')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Afișează ce ar sincroniza fără a scrie');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('DB → Vault Sync');

        $articleId = $input->getOption('article-id');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('DRY RUN — nu se scrie nimic pe disc');
        }

        // Single article mode
        if ($articleId !== null) {
            return $this->syncSingleArticle($io, (int) $articleId, $dryRun);
        }

        // Batch mode
        $limit = (int) $input->getOption('limit');
        $offset = (int) $input->getOption('offset');
        $since = null;

        $sinceStr = $input->getOption('since');
        if ($sinceStr !== null) {
            try {
                $since = new \DateTimeImmutable($sinceStr);
            } catch (\Exception) {
                $io->error("Data invalidă: {$sinceStr}. Folosiți formatul YYYY-MM-DD.");

                return Command::FAILURE;
            }
        }

        if (!$input->getOption('all') && $since === null) {
            $io->warning('Specificați --all, --since=DATA, sau --article-id=ID');

            return Command::FAILURE;
        }

        if ($dryRun) {
            return $this->dryRunBatch($io, $limit, $offset, $since);
        }

        $batchResult = $this->syncService->syncAllToVault($limit, $offset, $since);

        $rows = array_map(fn (VaultSyncResult $r) => [
            $r->articleId,
            $r->vaultPath ?? '-',
            $r->action,
            $r->error ?? '-',
        ], $batchResult->results);

        $io->table(['ID', 'Vault Path', 'Action', 'Error'], $rows);

        $io->success(sprintf(
            'Sync complet: %d create, %d actualizate, %d omise, %d eșuate',
            $batchResult->created,
            $batchResult->updated,
            $batchResult->skipped,
            $batchResult->failed,
        ));

        return $batchResult->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function syncSingleArticle(SymfonyStyle $io, int $articleId, bool $dryRun): int
    {
        $article = $this->em->find(Article::class, $articleId);

        if ($article === null) {
            $io->error("Articolul #{$articleId} nu a fost găsit.");

            return Command::FAILURE;
        }

        if ($dryRun) {
            $frontmatter = $this->syncService->buildFrontmatter($article);
            $io->section("Frontmatter pentru articolul #{$articleId}");
            $io->writeln(json_encode($frontmatter, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $result = $this->syncService->syncArticleToVault($article);

        if ($result->isSuccess()) {
            $io->success("Articol #{$articleId} sincronizat: {$result->action} → {$result->vaultPath}");

            return Command::SUCCESS;
        }

        $io->error("Eșec sincronizare #{$articleId}: {$result->error}");

        return Command::FAILURE;
    }

    private function dryRunBatch(SymfonyStyle $io, int $limit, int $offset, ?\DateTimeImmutable $since): int
    {
        $qb = $this->em->getRepository(Article::class)->createQueryBuilder('a')
            ->select('a.id', 'a.title', 'a.slug', 'a.status', 'a.createdAt')
            ->where('a.status = :status')
            ->setParameter('status', \App\Enum\ArticleStatus::PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($since !== null) {
            $qb->andWhere('a.updatedAt >= :since')
                ->setParameter('since', $since);
        }

        $articles = $qb->getQuery()->getResult();
        $total = \count($articles);

        $rows = array_map(fn (array $a) => [
            $a['id'],
            mb_substr($a['title'] ?? '', 0, 60),
            sprintf('articles/%s/%s/%s.md',
                $a['createdAt']?->format('Y') ?? '????',
                $a['createdAt']?->format('m') ?? '??',
                $a['slug'] ?? 'untitled',
            ),
            'would sync',
        ], $articles);

        $io->table(['ID', 'Title', 'Vault Path', 'Action'], $rows);
        $io->info("DRY RUN: {$total} articole ar fi sincronizate.");

        return Command::SUCCESS;
    }
}
