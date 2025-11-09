<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<Tag>
 */
final class TagProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Tag
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
            if ($data instanceof Tag) {
                // Check if tag is being used by any articles
                if ($data->getUsageCount() > 0) {
                    throw new LogicException(
                        sprintf(
                            'Cannot delete tag "%s" because it is currently used by %d article(s). Remove the tag from all articles first.',
                            $data->getName(),
                            $data->getUsageCount()
                        )
                    );
                }

                $this->entityManager->remove($data);
                $this->entityManager->flush();
            }

            return null;
        }

        // For POST and PUT operations
        if ($data instanceof Tag) {
            // Check if this is an update (PUT) by looking at URI variables
            $isUpdate = isset($uriVariables['id']);

            if ($isUpdate) {
                // Load existing entity for updates
                $existingEntity = $this->entityManager->getRepository(Tag::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new RuntimeException('Tag not found');
                }

                // Update fields from deserialized data
                $existingEntity->setName($data->getName());
                if ($data->getDescription() !== null) {
                    $existingEntity->setDescription($data->getDescription());
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

    private function addTranslation(Tag $tag, string $locale): void
    {
        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->entityManager->getRepository('Gedmo\Translatable\Entity\Translation');

        if ($tag->getName()) {
            $translationRepo->translate($tag, 'name', $locale, $tag->getName());
        }

        if ($tag->getSlug()) {
            $translationRepo->translate($tag, 'slug', $locale, $tag->getSlug());
        }

        if ($tag->getDescription()) {
            $translationRepo->translate($tag, 'description', $locale, $tag->getDescription());
        }

        $this->entityManager->flush();
    }
}
