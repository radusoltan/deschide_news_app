<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Image;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<Image>
 */
final class ImageProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Image
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
            if ($data instanceof Image) {
                // TODO: Delete physical file from storage
                $this->entityManager->remove($data);
                $this->entityManager->flush();
            }
            return null;
        }

        // For POST and PUT operations
        if ($data instanceof Image) {
            // Check if this is an update (PUT) by looking at URI variables
            $isUpdate = isset($uriVariables['id']);

            if (!$isUpdate) {
                // CREATE: Set default locale and persist
                $data->setTranslatableLocale($locale);

                // Vich will automatically handle file upload and set:
                // - filename (via fileNameProperty)
                // - originalFilename (via originalName)
                // - size (via size)
                // - mimeType (via mimeType)
                // - width and height (via dimensions)

                $this->entityManager->persist($data);
                $this->entityManager->flush();

                return $data;
            }

            if ($isUpdate) {
                // Load existing entity for updates
                $existingEntity = $this->entityManager->getRepository(Image::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new \RuntimeException('Image not found');
                }

                // Update translatable and metadata fields from deserialized data
                // File information fields (filename, path, etc.) are not updatable after creation
                if ($data->getAlt() !== null) {
                    $existingEntity->setAlt($data->getAlt());
                }
                if ($data->getCaption() !== null) {
                    $existingEntity->setCaption($data->getCaption());
                }
                if ($data->getDescription() !== null) {
                    $existingEntity->setDescription($data->getDescription());
                }
                // imageAuthor is not translatable, so update directly
                $existingEntity->setImageAuthor($data->getImageAuthor());

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

    private function addTranslation(Image $image, string $locale): void
    {
        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->entityManager->getRepository('Gedmo\\Translatable\\Entity\\Translation');

        if ($image->getAlt()) {
            $translationRepo->translate($image, 'alt', $locale, $image->getAlt());
        }

        if ($image->getCaption()) {
            $translationRepo->translate($image, 'caption', $locale, $image->getCaption());
        }

        if ($image->getDescription()) {
            $translationRepo->translate($image, 'description', $locale, $image->getDescription());
        }

        $this->entityManager->flush();
    }
}
