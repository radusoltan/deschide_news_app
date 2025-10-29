<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Service\ElasticService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:elasticsearch:index-articles',
    description: 'Index all existing articles in Elasticsearch',
)]
class ElasticsearchIndexArticlesCommand extends Command
{
    private array $supportedLocales = ['ro', 'en', 'ru'];

    public function __construct(
        private readonly ElasticService $elasticService,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('locale', 'l', InputOption::VALUE_OPTIONAL, 'Index articles for specific locale only', null)
            ->addOption('status', 's', InputOption::VALUE_OPTIONAL, 'Index only articles with specific status', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locale = $input->getOption('locale');
        $status = $input->getOption('status');

        if (!$this->elasticService->isEnabled()) {
            $io->warning('Elasticsearch is disabled. Check ELASTICSEARCH_HOST configuration.');
            return Command::SUCCESS;
        }

        try {
            $locales = $locale ? [$locale] : $this->supportedLocales;
            $totalIndexed = 0;

            foreach ($locales as $currentLocale) {
                $io->section(sprintf('Indexing articles for locale: %s', $currentLocale));

                // Fetch articles from database
                $qb = $this->entityManager->getRepository(Article::class)->createQueryBuilder('a');
                $qb->leftJoin('a.authors', 'authors')
                    ->leftJoin('a.category', 'category')
                    ->leftJoin('a.relatedArticles', 'related')
                    ->addSelect('authors', 'category', 'related');

                if ($status) {
                    $qb->where('a.status = :status')
                        ->setParameter('status', ArticleStatus::from($status));
                }

                $articles = $qb->getQuery()->getResult();

                $io->info(sprintf('Found %d articles to index', count($articles)));

                $indexed = 0;
                foreach ($articles as $article) {
                    // Set locale for translatable fields
                    $article->setTranslatableLocale($currentLocale);
                    $this->entityManager->refresh($article);

                    // Build authors array
                    $authors = [];
                    foreach ($article->getAuthors() as $author) {
                        $authors[] = [
                            'id' => $author->getId(),
                            'name' => $author->getFullName(),
                        ];
                    }

                    // Build related article IDs
                    $relatedIds = [];
                    foreach ($article->getRelatedArticles() as $related) {
                        $relatedIds[] = $related->getId();
                    }

                    // Build suggest input: title + category name + keywords
                    $suggestInput = [$article->getTitle()];

                    if ($article->getCategory()) {
                        $suggestInput[] = $article->getCategory()->getTitle();
                    }

                    // Extract first few words from lead/content as additional keywords
                    $text = $article->getLead() ?? $article->getContent() ?? '';
                    if ($text) {
                        $contentWords = str_word_count(strip_tags($text), 1);
                        $keywords = array_slice($contentWords, 0, 10);
                        $suggestInput = array_merge($suggestInput, $keywords);
                    }

                    $document = [
                        'id' => $article->getId(),
                        'title' => $article->getTitle(),
                        'slug' => $article->getSlug(),
                        'lead' => $article->getLead(),
                        'content' => $article->getContent(),
                        'suggest' => [
                            'input' => array_values(array_unique(array_filter($suggestInput))),
                            'weight' => $article->getViewCount() + 10,
                        ],
                        'locale' => $currentLocale,
                        'view_count' => $article->getViewCount(),
                        'published_at' => $article->getPublishedAt()?->format('c'),
                        'publish_at' => $article->getPublishAt()?->format('c'),
                        'created_at' => $article->getCreatedAt()->format('c'),
                        'authors' => $authors,
                        'category' => $article->getCategory() ? [
                            'id' => $article->getCategory()->getId(),
                            'name' => $article->getCategory()->getTitle(),
                            'slug' => $article->getCategory()->getSlug(),
                        ] : null,
                        'badge' => $article->getBadge()?->value,
                        'is_featured' => $article->isFeatured(),
                        'status' => $article->getStatus()->value,
                        'related_ids' => $relatedIds,
                    ];

                    $this->elasticService->indexDocument($document, $currentLocale);
                    ++$indexed;

                    if (0 === $indexed % 10) {
                        $io->text(sprintf('Indexed %d articles...', $indexed));
                    }
                }

                $io->success(sprintf('Successfully indexed %d articles for locale "%s"!', $indexed, $currentLocale));
                $totalIndexed += $indexed;
            }

            $io->success(sprintf('Total articles indexed: %d', $totalIndexed));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to index articles: '.$e->getMessage());
            $io->text('Stack trace: '.$e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}
