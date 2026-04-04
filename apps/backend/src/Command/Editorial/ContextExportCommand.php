<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use App\Entity\Article;
use App\Entity\Topic;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Service\Editorial\ArticleContextService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:context:export',
    description: 'Export article context for AI agents (markdown or JSON to stdout)',
)]
final class ContextExportCommand extends Command
{
    public function __construct(
        private readonly ArticleContextService $contextService,
        private readonly ArticleRepository $articleRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'Export type: article, articles, briefing, topics')
            ->addArgument('id', InputArgument::OPTIONAL, 'Article ID (for type=article)')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: markdown or json', 'markdown')
            ->addOption('locale', 'l', InputOption::VALUE_REQUIRED, 'Locale: ro, en, ru', 'ro')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write to file instead of stdout')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max articles for batch export', '20')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only articles published since (ISO 8601)')
            ->addOption('category', null, InputOption::VALUE_REQUIRED, 'Filter by category slug')
            ->addOption('topic', null, InputOption::VALUE_REQUIRED, 'Filter by topic slug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = $input->getArgument('type');
        $format = $input->getOption('format');
        $locale = $input->getOption('locale');

        if (!\in_array($format, ['markdown', 'json'], true)) {
            fwrite(\STDERR, "Invalid format: {$format}. Use 'markdown' or 'json'.\n");

            return Command::INVALID;
        }

        if (!\in_array($locale, ['ro', 'en', 'ru'], true)) {
            fwrite(\STDERR, "Invalid locale: {$locale}. Use 'ro', 'en', or 'ru'.\n");

            return Command::INVALID;
        }

        $result = match ($type) {
            'article' => $this->exportArticle($input, $format, $locale),
            'articles' => $this->exportArticles($input, $format, $locale),
            'briefing' => $this->exportBriefing($format),
            'topics' => $this->exportTopics($format),
            default => null,
        };

        if ($result === null) {
            fwrite(\STDERR, "Invalid type: {$type}. Use 'article', 'articles', 'briefing', or 'topics'.\n");

            return Command::INVALID;
        }

        $outputPath = $input->getOption('output');
        if ($outputPath !== null) {
            file_put_contents($outputPath, $result);
            fwrite(\STDERR, "Written to {$outputPath}\n");
        } else {
            $output->write($result);
        }

        return Command::SUCCESS;
    }

    private function exportArticle(InputInterface $input, string $format, string $locale): ?string
    {
        $id = $input->getArgument('id');
        if ($id === null) {
            fwrite(\STDERR, "Article ID is required for type=article.\n");

            return null;
        }

        $article = $this->articleRepository->find((int) $id);
        if ($article === null) {
            fwrite(\STDERR, "Article #{$id} not found.\n");

            return null;
        }

        if ($format === 'json') {
            $data = $this->contextService->buildJsonForArticle($article, $locale);

            return json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
        }

        return $this->contextService->buildMarkdownForArticle($article, $locale);
    }

    private function exportArticles(InputInterface $input, string $format, string $locale): string
    {
        $limit = (int) $input->getOption('limit');
        $sinceStr = $input->getOption('since');
        $categorySlug = $input->getOption('category');
        $topicSlug = $input->getOption('topic');

        $qb = $this->articleRepository->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit);

        if ($sinceStr !== null) {
            $since = new \DateTimeImmutable($sinceStr);
            $qb->andWhere('a.publishedAt >= :since')
                ->setParameter('since', $since);
        }

        if ($categorySlug !== null) {
            $qb->leftJoin('a.category', 'c')
                ->andWhere('c.slug = :catSlug')
                ->setParameter('catSlug', $categorySlug);
        }

        if ($topicSlug !== null) {
            $qb->leftJoin('a.topics', 't')
                ->andWhere('t.slug = :topicSlug')
                ->setParameter('topicSlug', $topicSlug);
        }

        /** @var list<Article> $articles */
        $articles = $qb->getQuery()->getResult();

        if ($format === 'json') {
            $data = array_map(
                fn (Article $a) => $this->contextService->buildJsonForArticle($a, $locale),
                $articles,
            );

            return json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
        }

        $parts = [];
        foreach ($articles as $article) {
            $parts[] = $this->contextService->buildMarkdownForArticle($article, $locale);
        }

        return implode("\n---\n\n", $parts);
    }

    private function exportBriefing(string $format): string
    {
        try {
            $repo = $this->em->getRepository(\App\Entity\GeneratedContent::class);
            $briefings = $repo->findBy(
                ['type' => 'daily_briefing'],
                ['generatedAt' => 'DESC'],
                5,
            );
        } catch (\Throwable) {
            // GeneratedContent entity may not exist yet (created in T23.3)
            $briefings = [];
        }

        if ($briefings === []) {
            return $format === 'json' ? '[]' : 'No briefings found.';
        }

        if ($format === 'json') {
            $data = array_map(fn ($b) => [
                'id' => $b->getId(),
                'type' => $b->getType(),
                'title' => $b->getTitle(),
                'content' => $b->getContent(),
                'metadata' => $b->getMetadata(),
                'generatedAt' => $b->getGeneratedAt()->format('c'),
                'locale' => $b->getLocale(),
            ], $briefings);

            return json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
        }

        $parts = [];
        foreach ($briefings as $b) {
            $parts[] = "# {$b->getTitle()}\n\n*Generated: {$b->getGeneratedAt()->format('Y-m-d')}*\n\n{$b->getContent()}";
        }

        return implode("\n\n---\n\n", $parts);
    }

    private function exportTopics(string $format): string
    {
        $topics = $this->em->getRepository(Topic::class)->findBy([], ['title' => 'ASC']);

        if ($topics === []) {
            return $format === 'json' ? '[]' : 'No topics found.';
        }

        if ($format === 'json') {
            $data = array_map(fn (Topic $t) => [
                'id' => $t->getId(),
                'title' => $t->getTitle(),
                'slug' => $t->getSlug(),
                'description' => $t->getDescription(),
            ], $topics);

            return json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);
        }

        $lines = ["# Topics\n"];
        foreach ($topics as $topic) {
            $desc = $topic->getDescription() ? " — {$topic->getDescription()}" : '';
            $lines[] = "- **{$topic->getTitle()}** (`{$topic->getSlug()}`){$desc}";
        }

        return implode("\n", $lines) . "\n";
    }
}
