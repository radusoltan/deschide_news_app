<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\TestArticle;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<TestArticle>
 */
final class TestArticleProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?TestArticle
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
            $this->entityManager->remove($data);
            $this->entityManager->flush();
            return null;
        }

        // For POST and PUT operations
        if ($data instanceof TestArticle) {
            // Check if this is an update (PUT) by looking at URI variables
            $isUpdate = isset($uriVariables['id']);

            if ($isUpdate) {
                // Load existing entity for updates
                $existingEntity = $this->entityManager->getRepository(TestArticle::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new \RuntimeException('Entity not found');
                }

                // Update fields from deserialized data
                $existingEntity->setTitle($data->getTitle());
                $existingEntity->setDescription($data->getDescription());
                if ($data->getStatus()) {
                    $existingEntity->setStatus($data->getStatus());
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
                    /** @var TranslationRepository $translationRepo */
                    $translationRepo = $this->entityManager->getRepository('Gedmo\\Translatable\\Entity\\Translation');

                    if ($data->getTitle()) {
                        $translationRepo->translate($data, 'title', $locale, $data->getTitle());
                    }

                    if ($data->getDescription()) {
                        $translationRepo->translate($data, 'description', $locale, $data->getDescription());
                    }

                    $this->entityManager->flush();
                }
            } else {
                // UPDATE: Existing entity
                if ($locale === 'ro') {
                    // Update default locale fields directly
                    $data->setTranslatableLocale($locale);
                    $this->entityManager->flush();
                } else {
                    // Add/Update translation for non-default locale
                    /** @var TranslationRepository $translationRepo */
                    $translationRepo = $this->entityManager->getRepository('Gedmo\\Translatable\\Entity\\Translation');

                    if ($data->getTitle()) {
                        $translationRepo->translate($data, 'title', $locale, $data->getTitle());
                    }

                    if ($data->getDescription()) {
                        $translationRepo->translate($data, 'description', $locale, $data->getDescription());
                    }

                    $this->entityManager->flush();
                }

                // Reload entity with correct locale
                $data->setTranslatableLocale($locale);
                $this->entityManager->refresh($data);
            }

            return $data;
        }

        return null;
    }
}
