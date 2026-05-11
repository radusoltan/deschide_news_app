<?php

declare(strict_types=1);

namespace App\State\Article;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Entity\Category;
use App\Enum\ArticleStatus;
use App\Event\ArticlePublishedEvent;
use App\Event\ArticleUpdatedEvent;
use App\Service\Article\ArticleCacheInvalidator;
use App\Service\Article\CollectionSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Handles PUT/PATCH (update) operations for Article entities.
 *
 * @implements ProcessorInterface<Article>
 */
final class ArticleUpdateProcessor implements ProcessorInterface
{
    use ArticleProcessorTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly CollectionSyncService $collectionSync,
        private readonly ArticleCacheInvalidator $cacheInvalidator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(MERCURE_URL)%')]
        private readonly string $mercureUrl = '',
        #[Autowire('%env(MERCURE_JWT_SECRET)%')]
        private readonly string $mercureJwtSecret = '',
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Article
    {
        if (!$data instanceof Article) {
            return null;
        }

        $locale = $this->resolveLocale($this->requestStack);

        $repository = $this->entityManager->getRepository(Article::class);
        $existingEntity = $repository->find($uriVariables['id']);

        if (!$existingEntity) {
            throw new RuntimeException('Article not found');
        }

        $oldStatus = $existingEntity->getStatus();

        // Set translatable locale before making changes
        $existingEntity->setTranslatableLocale($locale);

        // Update translatable fields
        if ($data->getTitle()) {
            $existingEntity->setTitle($data->getTitle());
        }
        if ($data->getLead() !== null) {
            $existingEntity->setLead($data->getLead());
        }
        if ($data->getContent() !== null) {
            $existingEntity->setContent($data->getContent());
        }

        // Update non-translatable fields
        $existingEntity->setStatus($data->getStatus());
        $existingEntity->setBadge($data->getBadge());
        $existingEntity->setIsFeatured($data->isFeatured());

        // Update publishAt
        if ($data->getPublishAt() !== null) {
            $existingEntity->setPublishAt($data->getPublishAt());
        } elseif ($data->getStatus() !== ArticleStatus::SUBMITTED) {
            $existingEntity->setPublishAt(null);
        }

        // Update category
        if ($data->getCategory()) {
            $managed = $this->entityManager->getRepository(Category::class)->find($data->getCategory()->getId());
            $existingEntity->setCategory($managed);
        }

        // Sync all collections
        $this->collectionSync->syncAuthors($existingEntity, $data);
        $this->collectionSync->syncRelatedArticles($existingEntity, $data);
        $this->collectionSync->syncTags($existingEntity, $data);
        $this->collectionSync->syncTopics($existingEntity, $data);

        // Flush changes — locale-dependent
        if ($locale === 'ro') {
            $this->entityManager->flush();
        } else {
            $this->entityManager->flush();

            /** @var TranslationRepository $translationRepo */
            $translationRepo = $this->entityManager->getRepository('Gedmo\Translatable\Entity\Translation');

            if ($existingEntity->getTitle()) {
                $translationRepo->translate($existingEntity, 'title', $locale, $existingEntity->getTitle());
            }

            if ($existingEntity->getLead()) {
                $translationRepo->translate($existingEntity, 'lead', $locale, $existingEntity->getLead());
            }

            if ($existingEntity->getContent()) {
                $translationRepo->translate($existingEntity, 'content', $locale, $existingEntity->getContent());
            }

            $this->entityManager->flush();
        }

        // Invalidate cache
        if ($existingEntity->getId()) {
            $this->cacheInvalidator->invalidate($existingEntity->getId());
        }

        // Notify public frontends via Mercure SSE
        $this->publishArticleUpdateEvent($existingEntity, 'updated', $this->mercureUrl, $this->mercureJwtSecret, $this->httpClient, $this->logger);

        // Dispatch notification events
        $newStatus = $existingEntity->getStatus();
        if ($oldStatus !== ArticleStatus::PUBLISHED && $newStatus === ArticleStatus::PUBLISHED) {
            $this->eventDispatcher->dispatch(new ArticlePublishedEvent($existingEntity));
        } elseif ($newStatus === ArticleStatus::PUBLISHED) {
            $this->eventDispatcher->dispatch(new ArticleUpdatedEvent($existingEntity));
        }

        return $existingEntity;
    }
}
