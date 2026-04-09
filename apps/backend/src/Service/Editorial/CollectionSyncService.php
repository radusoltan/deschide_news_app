<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Tag;
use App\Entity\Topic;
use Doctrine\ORM\EntityManagerInterface;

final class CollectionSyncService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Sync authors collection on an existing article.
     * Removes authors not in the incoming data, adds new ones as managed entities.
     */
    public function syncAuthors(Article $article, Article $data): void
    {
        $incomingIds = [];
        foreach ($data->getAuthors() as $author) {
            if ($author->getId()) {
                $incomingIds[] = $author->getId();
            }
        }

        foreach ($article->getAuthors() as $author) {
            if (!\in_array($author->getId(), $incomingIds, true)) {
                $article->removeAuthor($author);
            }
        }

        $existingIds = [];
        foreach ($article->getAuthors() as $author) {
            $existingIds[] = $author->getId();
        }
        foreach ($data->getAuthors() as $author) {
            if (!\in_array($author->getId(), $existingIds, true)) {
                $managed = $this->getManagedEntity(Author::class, $author->getId());
                if ($managed) {
                    $article->addAuthor($managed);
                }
            }
        }
    }

    /**
     * Sync tags collection on an existing article.
     * Handles usage count decrement/increment.
     */
    public function syncTags(Article $article, Article $data): void
    {
        $incomingIds = [];
        foreach ($data->getTags() as $tag) {
            if ($tag->getId()) {
                $incomingIds[] = $tag->getId();
            }
        }

        foreach ($article->getTags() as $tag) {
            if (!\in_array($tag->getId(), $incomingIds, true)) {
                $article->removeTag($tag);
                $tag->setUsageCount(max(0, $tag->getUsageCount() - 1));
            }
        }

        $existingIds = [];
        foreach ($article->getTags() as $tag) {
            $existingIds[] = $tag->getId();
        }
        foreach ($data->getTags() as $tag) {
            if (!\in_array($tag->getId(), $existingIds, true)) {
                $managed = $this->getManagedEntity(Tag::class, $tag->getId());
                if ($managed) {
                    $article->addTag($managed);
                    $managed->setUsageCount($managed->getUsageCount() + 1);
                }
            }
        }
    }

    /**
     * Sync topics collection on an existing article.
     */
    public function syncTopics(Article $article, Article $data): void
    {
        $incomingIds = [];
        foreach ($data->getTopics() as $topic) {
            if ($topic->getId()) {
                $incomingIds[] = $topic->getId();
            }
        }

        foreach ($article->getTopics()->toArray() as $topic) {
            if (!\in_array($topic->getId(), $incomingIds, true)) {
                $article->removeTopic($topic);
            }
        }

        $existingIds = [];
        foreach ($article->getTopics() as $topic) {
            $existingIds[] = $topic->getId();
        }
        foreach ($data->getTopics() as $topic) {
            if (!\in_array($topic->getId(), $existingIds, true)) {
                $managed = $this->getManagedEntity(Topic::class, $topic->getId());
                if ($managed) {
                    $article->addTopic($managed);
                }
            }
        }
    }

    /**
     * Sync related articles collection on an existing article.
     */
    public function syncRelatedArticles(Article $article, Article $data): void
    {
        foreach ($article->getRelatedArticles() as $relatedArticle) {
            if (!$data->getRelatedArticles()->contains($relatedArticle)) {
                $article->removeRelatedArticle($relatedArticle);
            }
        }
        foreach ($data->getRelatedArticles() as $relatedArticle) {
            if (!$article->getRelatedArticles()->contains($relatedArticle)) {
                $article->addRelatedArticle($relatedArticle);
            }
        }
    }

    /**
     * Replace collections with managed entities on a new (not yet persisted) article.
     * Used during CREATE to avoid "new entity found through relationship" errors.
     */
    public function replaceWithManagedEntities(Article $article): void
    {
        $this->replaceAuthors($article);
        $this->replaceTags($article);
        $this->replaceTopics($article);
    }

    private function replaceAuthors(Article $article): void
    {
        $managed = [];
        foreach ($article->getAuthors() as $author) {
            $managed[] = $this->getManagedEntity(Author::class, $author->getId());
        }
        foreach ($article->getAuthors()->toArray() as $author) {
            $article->removeAuthor($author);
        }
        foreach ($managed as $author) {
            if ($author) {
                $article->addAuthor($author);
            }
        }
    }

    private function replaceTags(Article $article): void
    {
        $managed = [];
        foreach ($article->getTags() as $tag) {
            $managed[] = $this->getManagedEntity(Tag::class, $tag->getId());
        }
        foreach ($article->getTags()->toArray() as $tag) {
            $article->removeTag($tag);
        }
        foreach ($managed as $tag) {
            if ($tag) {
                $article->addTag($tag);
            }
        }
    }

    private function replaceTopics(Article $article): void
    {
        $managed = [];
        foreach ($article->getTopics() as $topic) {
            $managed[] = $this->getManagedEntity(Topic::class, $topic->getId());
        }
        foreach ($article->getTopics()->toArray() as $topic) {
            $article->removeTopic($topic);
        }
        foreach ($managed as $topic) {
            if ($topic) {
                $article->addTopic($topic);
            }
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    private function getManagedEntity(string $class, ?int $id): ?object
    {
        if (!$id) {
            return null;
        }

        return $this->entityManager->getRepository($class)->find($id);
    }
}
