<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Entity\Topic;
use App\Repository\TopicRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

class TopicService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TopicRepository $topicRepository,
    ) {}

    public function createTopic(string $title, ?Topic $parent = null, ?string $description = null): Topic
    {
        $topic = new Topic();
        $topic->setTitle($title);
        $topic->setDescription($description);
        $topic->setParent($parent);

        $this->entityManager->persist($topic);
        $this->entityManager->flush();

        return $topic;
    }

    /**
     * @param array{title?: string, description?: string|null, parent?: Topic|null, position?: int, isActive?: bool} $data
     */
    public function updateTopic(Topic $topic, array $data): Topic
    {
        if (isset($data['title'])) {
            $topic->setTitle($data['title']);
        }
        if (\array_key_exists('description', $data)) {
            $topic->setDescription($data['description']);
        }
        if (\array_key_exists('parent', $data)) {
            $topic->setParent($data['parent']);
        }
        if (isset($data['position'])) {
            $topic->setPosition($data['position']);
        }
        if (isset($data['isActive'])) {
            $topic->setIsActive($data['isActive']);
        }

        $this->entityManager->flush();

        return $topic;
    }

    /**
     * @throws \RuntimeException if topic has associated articles
     */
    public function deleteTopic(Topic $topic): void
    {
        $articleCount = $this->topicRepository->getArticleCountForTopic($topic, false);
        if ($articleCount > 0) {
            throw new \RuntimeException(
                \sprintf('Cannot delete topic "%s": it has %d associated article(s). Remove the associations first.', $topic->getTitle(), $articleCount)
            );
        }

        $this->entityManager->remove($topic);
        $this->entityManager->flush();
    }

    /**
     * Move a topic to a new parent and/or position using Gedmo NestedSet.
     */
    public function moveTopic(Topic $topic, ?Topic $newParent, int $position): void
    {
        $topic->setParent($newParent);
        $topic->setPosition($position);

        $this->entityManager->persist($topic);
        $this->entityManager->flush();
    }

    /**
     * Get the full tree formatted for API responses.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTreeForApi(?string $locale = null): array
    {
        return $this->topicRepository->getFullTree($locale);
    }

    /**
     * @return Collection<int, Topic>
     */
    public function getTopicsForArticle(Article $article): Collection
    {
        return $article->getTopics();
    }

    /**
     * Sync article topics by topic IDs (similar to tag sync pattern).
     *
     * @param int[] $topicIds
     */
    public function syncArticleTopics(Article $article, array $topicIds): void
    {
        // Remove topics not in the new list
        foreach ($article->getTopics()->toArray() as $topic) {
            if (!\in_array($topic->getId(), $topicIds, true)) {
                $article->removeTopic($topic);
            }
        }

        // Add new topics
        $existingIds = array_map(fn (Topic $t) => $t->getId(), $article->getTopics()->toArray());
        foreach ($topicIds as $topicId) {
            if (!\in_array($topicId, $existingIds, true)) {
                $topic = $this->topicRepository->find($topicId);
                if ($topic) {
                    $article->addTopic($topic);
                }
            }
        }

        $this->entityManager->flush();
    }

    /**
     * Get the breadcrumb path for a topic (array of ancestors from root to self).
     *
     * @return Topic[]
     */
    public function getPath(Topic $topic): array
    {
        return $this->topicRepository->getPath($topic);
    }

    /**
     * Get a flat list of topics with indentation info (for dropdowns).
     *
     * @return array<int, array{id: int, title: string, slug: string, lvl: int, indent: string}>
     */
    public function getFlatList(?string $locale = null): array
    {
        $qb = $this->topicRepository->createQueryBuilder('t')
            ->where('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.root', 'ASC')
            ->addOrderBy('t.lft', 'ASC');

        $query = $qb->getQuery();
        if ($locale !== null) {
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );
        }

        $topics = $query->getResult();
        $result = [];

        foreach ($topics as $topic) {
            $result[] = [
                'id' => $topic->getId(),
                'title' => $topic->getTitle(),
                'slug' => $topic->getSlug(),
                'lvl' => $topic->getLvl(),
                'indent' => str_repeat('— ', $topic->getLvl()),
            ];
        }

        return $result;
    }
}
