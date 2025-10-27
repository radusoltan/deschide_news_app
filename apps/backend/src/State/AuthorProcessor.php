<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Author;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<Author>
 */
final class AuthorProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Author
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
            if ($data instanceof Author) {
                $this->entityManager->remove($data);
                $this->entityManager->flush();
            }
            return null;
        }

        // For POST and PUT operations
        if ($data instanceof Author) {
            // Check if this is an update (PUT) by looking at URI variables
            $isUpdate = isset($uriVariables['id']);

            if ($isUpdate) {
                // Load existing entity for updates
                $existingEntity = $this->entityManager->getRepository(Author::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new \RuntimeException('Author not found');
                }

                // Update fields from deserialized data
                $existingEntity->setFirstName($data->getFirstName());
                $existingEntity->setLastName($data->getLastName());
                // Only update email if provided and changed (to avoid UniqueEntity validation error)
                if ($data->getEmail() !== null && $data->getEmail() !== $existingEntity->getEmail()) {
                    $existingEntity->setEmail($data->getEmail());
                }
                if ($data->getBio() !== null) {
                    $existingEntity->setBio($data->getBio());
                }
                $existingEntity->setStatus($data->getStatus());
                $existingEntity->setIsActive($data->isActive());
                $existingEntity->setTwitter($data->getTwitter());
                $existingEntity->setFacebook($data->getFacebook());
                $existingEntity->setLinkedin($data->getLinkedin());
                $existingEntity->setWebsite($data->getWebsite());

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

    private function addTranslation(Author $author, string $locale): void
    {
        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->entityManager->getRepository('Gedmo\\Translatable\\Entity\\Translation');

        if ($author->getBio()) {
            $translationRepo->translate($author, 'bio', $locale, $author->getBio());
        }

        $this->entityManager->flush();
    }
}
