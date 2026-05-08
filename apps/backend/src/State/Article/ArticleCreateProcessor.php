<?php

declare(strict_types=1);

namespace App\State\Article;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Entity\Category;
use App\Enum\ArticleStatus;
use App\Event\ArticlePublishedEvent;
use App\Service\Article\CollectionSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Handles POST (create) operations for Article entities.
 *
 * @implements ProcessorInterface<Article>
 */
final class ArticleCreateProcessor implements ProcessorInterface
{
    use ArticleProcessorTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly CollectionSyncService $collectionSync,
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

        // CREATE: New entity - always save in default locale
        $data->setTranslatableLocale('ro');

        // Get managed category
        if ($data->getCategory()) {
            $managed = $this->entityManager->getRepository(Category::class)->find($data->getCategory()->getId());
            $data->setCategory($managed);
        }

        // Replace detached collections with managed entities
        $this->collectionSync->replaceWithManagedEntities($data);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        // Increment usage count for all tags on new article
        foreach ($data->getTags() as $tag) {
            $tag->setUsageCount($tag->getUsageCount() + 1);
        }
        $this->entityManager->flush();

        // If created with non-default locale, also add translation
        if ($locale !== 'ro') {
            $this->addTranslation($data, $locale);
        }

        // Notify public frontends via Mercure SSE
        $this->publishArticleUpdateEvent($data, 'created', $this->mercureUrl, $this->mercureJwtSecret, $this->httpClient, $this->logger);

        // Dispatch notification for new published articles
        if ($data->getStatus() === ArticleStatus::PUBLISHED) {
            $this->eventDispatcher->dispatch(new ArticlePublishedEvent($data));
        }

        return $data;
    }

    private function addTranslation(Article $article, string $locale): void
    {
        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->entityManager->getRepository('Gedmo\Translatable\Entity\Translation');

        if ($article->getTitle()) {
            $translationRepo->translate($article, 'title', $locale, $article->getTitle());
        }

        if ($article->getLead()) {
            $translationRepo->translate($article, 'lead', $locale, $article->getLead());
        }

        if ($article->getContent()) {
            $translationRepo->translate($article, 'content', $locale, $article->getContent());
        }

        $this->entityManager->flush();
    }
}
