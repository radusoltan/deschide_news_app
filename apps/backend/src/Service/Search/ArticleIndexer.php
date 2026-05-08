<?php

declare(strict_types=1);

namespace App\Service\Search;

use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Psr\Log\LoggerInterface;

final readonly class ArticleIndexer
{
    public function __construct(
        private ElasticsearchIndexManager $indexManager,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    /**
     * Index a single article with all available translations.
     */
    public function index(Article $article): void
    {
        if (!$this->indexManager->isEnabled()) {
            return;
        }

        $document = $this->buildDocument($article);

        try {
            $this->indexManager->getClient()->index([
                'index' => $this->indexManager->getIndexName(),
                'id' => (string) $article->getId(),
                'body' => $document,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('ArticleIndexer: failed to index article', [
                'articleId' => $article->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove an article from the index.
     */
    public function remove(int $articleId): void
    {
        if (!$this->indexManager->isEnabled()) {
            return;
        }

        try {
            $this->indexManager->getClient()->delete([
                'index' => $this->indexManager->getIndexName(),
                'id' => (string) $articleId,
            ]);
        } catch (\Throwable $e) {
            $this->logger->debug('ArticleIndexer: failed to remove article (may not exist)', [
                'articleId' => $articleId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the Elasticsearch document with trilingual fields.
     *
     * @return array<string, mixed>
     */
    private function buildDocument(Article $article): array
    {
        // Get translations from Gedmo
        $translationRepo = $this->em->getRepository(Translation::class);
        $translations = $translationRepo->findTranslations($article);

        // Default locale (ro) comes from the entity directly
        $doc = [
            'article_id' => $article->getId(),
            'title_ro' => $article->getTitle() ?? '',
            'body_ro' => strip_tags($article->getContent() ?? ''),
            'description_ro' => $article->getLead() ?? '',
            'status' => $article->getStatus()->value,
            'published_locales' => $article->getPublishedLocales(),
            'content_hash' => $article->getContentHash(),
            'date_created' => $article->getCreatedAt()?->format('c'),
            'date_published' => $article->getPublishedAt()?->format('c'),
        ];

        // Add translated fields
        foreach (['en', 'ru'] as $locale) {
            $t = $translations[$locale] ?? [];
            $doc["title_{$locale}"] = $t['title'] ?? '';
            $doc["body_{$locale}"] = strip_tags($t['content'] ?? '');
            $doc["description_{$locale}"] = $t['lead'] ?? '';
        }

        // Category
        $category = $article->getCategory();
        if ($category !== null) {
            $doc['categories'] = [$category->getSlug()];
        }

        // Tags
        $tagSlugs = [];
        foreach ($article->getTags() as $tag) {
            $tagSlugs[] = $tag->getSlug();
        }
        $doc['tags'] = $tagSlugs;

        // Authors
        $authorNames = [];
        foreach ($article->getAuthors() as $author) {
            $authorNames[] = $author->getFullName();
        }
        $doc['author'] = $authorNames;

        // Topics
        $topicIds = [];
        $topicTitles = [];
        $topicSlugs = [];
        foreach ($article->getTopics() as $topic) {
            $topicIds[] = $topic->getId();
            $topicTitles[] = $topic->getTitle();
            $topicSlugs[] = $topic->getSlug();
        }
        $doc['topic_ids'] = $topicIds;
        $doc['topic_titles'] = $topicTitles;
        $doc['topic_slugs'] = $topicSlugs;

        return $doc;
    }
}
