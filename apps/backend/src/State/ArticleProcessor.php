<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<Article>
 */
final class ArticleProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Article
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->headers->get('Accept-Language', 'ro') ?? 'ro';

        // Extract just the language code
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        // Handle DELETE operation
        if ($operation instanceof DeleteOperationInterface) {
            if ($data instanceof Article) {
                $this->entityManager->remove($data);
                $this->entityManager->flush();
            }
            return null;
        }

        // For POST and PUT operations
        if ($data instanceof Article) {
            // Check if this is an update (PUT) by looking at URI variables
            $isUpdate = isset($uriVariables['id']);

            if ($isUpdate) {
                // Load existing entity for updates
                $existingEntity = $this->entityManager->getRepository(Article::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new \RuntimeException('Article not found');
                }

                // Update fields from deserialized data
                $existingEntity->setTitle($data->getTitle());
                if ($data->getLead()) {
                    $existingEntity->setLead($data->getLead());
                }
                $existingEntity->setContent($data->getContent());
                $existingEntity->setStatus($data->getStatus());
                $existingEntity->setBadge($data->getBadge());
                $existingEntity->setIsFeatured($data->isFeatured());

                // Update publishAt if provided
                if ($data->getPublishAt() !== null) {
                    $existingEntity->setPublishAt($data->getPublishAt());
                }

                // Update category if provided
                if ($data->getCategory()) {
                    $existingEntity->setCategory($data->getCategory());
                }

                // Sync authors collection
                // Remove authors that are not in the new list
                foreach ($existingEntity->getAuthors() as $author) {
                    if (!$data->getAuthors()->contains($author)) {
                        $existingEntity->removeAuthor($author);
                    }
                }
                // Add new authors
                foreach ($data->getAuthors() as $author) {
                    if (!$existingEntity->getAuthors()->contains($author)) {
                        $existingEntity->addAuthor($author);
                    }
                }

                // Sync related articles collection
                // Remove related articles that are not in the new list
                foreach ($existingEntity->getRelatedArticles() as $relatedArticle) {
                    if (!$data->getRelatedArticles()->contains($relatedArticle)) {
                        $existingEntity->removeRelatedArticle($relatedArticle);
                    }
                }
                // Add new related articles
                foreach ($data->getRelatedArticles() as $relatedArticle) {
                    if (!$existingEntity->getRelatedArticles()->contains($relatedArticle)) {
                        $existingEntity->addRelatedArticle($relatedArticle);
                    }
                }

                // Use existing entity instead of deserialized one
                $data = $existingEntity;
            }

            $isNew = !$data->getId();

            if ($isNew) {
                // CREATE: New entity - always save in default locale
                $data->setTranslatableLocale('ro');
                $this->entityManager->persist($data);
                $this->entityManager->flush();

                // If created with non-default locale, also add translation
                if ($locale !== 'ro') {
                    $this->addTranslation($data, $locale);
                }
            } else {
                // UPDATE: Existing entity
                if ($locale === 'ro') {
                    // Update default locale fields directly
                    $data->setTranslatableLocale($locale);
                    $this->entityManager->flush();
                } else {
                    // Add/Update translation for non-default locale
                    $this->addTranslation($data, $locale);
                }

                // Reload entity with correct locale
                $data->setTranslatableLocale($locale);
                $this->entityManager->refresh($data);
            }

            return $data;
        }

        return null;
    }

    private function addTranslation(Article $article, string $locale): void
    {
        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->entityManager->getRepository('Gedmo\\Translatable\\Entity\\Translation');

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
