<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Image;
use App\Entity\ThumbnailProfile;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProcessorInterface<Image>
 */
final class ImageProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly ImageService $imageService
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
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

        // Handle custom crop operations
        $uriTemplate = $operation->getUriTemplate();

        if ($uriTemplate === '/images/{id}/thumbnails/crop') {
            return $this->handleCropOperation($data, $uriVariables);
        }

        if ($uriTemplate === '/images/{id}/thumbnails/reset-crop') {
            return $this->handleResetCropOperation($data, $uriVariables);
        }

        if ($uriTemplate === '/images/{id}/generate-thumbnails') {
            return $this->handleGenerateThumbnailsOperation($uriVariables);
        }

        // Handle DELETE operation
        if ($operation instanceof DeleteOperationInterface) {
            if ($data instanceof Image) {
                // Check if image is attached to any articles
                $articleImages = $data->getArticleImages();
                if ($articleImages && \count($articleImages) > 0) {
                    throw new RuntimeException(\sprintf('Cannot delete image: it is attached to %d article(s). Please detach it from all articles first.', \count($articleImages)));
                }

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
                    throw new RuntimeException('Image not found');
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
        $translationRepo = $this->entityManager->getRepository('Gedmo\Translatable\Entity\Translation');

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

    /**
     * Handle crop operation: POST /images/{id}/thumbnails/crop.
     *
     * Expected payload: {
     *   "profile": "article_card",
     *   "format": "webp",
     *   "cropData": {"x": 10, "y": 20, "width": 300, "height": 200}
     * }
     */
    private function handleCropOperation(mixed $data, array $uriVariables)
    {
        // Load image entity
        $imageId = $uriVariables['id'] ?? null;
        if (!$imageId) {
            throw new InvalidArgumentException('Image ID is required');
        }

        $image = $this->entityManager->getRepository(Image::class)->find($imageId);
        if (!$image) {
            throw new RuntimeException('Image not found');
        }

        // Extract request data from Image entity (denormalized with 'image:crop' group)
        if (!$data instanceof Image) {
            throw new InvalidArgumentException('Invalid request data - expected Image entity');
        }

        $profileName = $data->getProfile();
        $format = $data->getFormat() ?? 'webp';
        $cropData = $data->getCropData();

        if (!$profileName) {
            throw new InvalidArgumentException('Profile name is required');
        }

        if (!$cropData) {
            throw new InvalidArgumentException('Crop data is required');
        }

        // Load profile
        $profile = $this->entityManager->getRepository(ThumbnailProfile::class)
            ->findOneBy(['name' => $profileName]);

        if (!$profile) {
            throw new InvalidArgumentException(\sprintf('Profile "%s" not found', $profileName));
        }

        // Generate thumbnail with crop
        $thumbnail = $this->imageService->generateThumbnail($image, $profile, $format, $cropData);

        // Return the thumbnail (will be serialized with 'thumbnail:read' group)
        return $thumbnail;
    }

    /**
     * Handle reset crop operation: POST /images/{id}/thumbnails/reset-crop.
     *
     * Expected payload: {
     *   "profile": "article_card",
     *   "format": "webp"
     * }
     */
    private function handleResetCropOperation(mixed $data, array $uriVariables)
    {
        // Load image entity
        $imageId = $uriVariables['id'] ?? null;
        if (!$imageId) {
            throw new InvalidArgumentException('Image ID is required');
        }

        $image = $this->entityManager->getRepository(Image::class)->find($imageId);
        if (!$image) {
            throw new RuntimeException('Image not found');
        }

        // Extract request data from Image entity
        if (!$data instanceof Image) {
            throw new InvalidArgumentException('Invalid request data - expected Image entity');
        }

        $profileName = $data->getProfile();
        $format = $data->getFormat() ?? 'webp';

        if (!$profileName) {
            throw new InvalidArgumentException('Profile name is required');
        }

        // Load profile
        $profile = $this->entityManager->getRepository(ThumbnailProfile::class)
            ->findOneBy(['name' => $profileName]);

        if (!$profile) {
            throw new InvalidArgumentException(\sprintf('Profile "%s" not found', $profileName));
        }

        // Generate thumbnail WITHOUT crop (cropData = null)
        $thumbnail = $this->imageService->generateThumbnail($image, $profile, $format, null);

        // Return the thumbnail
        return $thumbnail;
    }

    /**
     * Handle generate-thumbnails operation: POST /images/{id}/generate-thumbnails.
     *
     * Generates all thumbnails for all active profiles in all formats (webp, jpg).
     * Uses auto-crop calculation for optimal framing.
     */
    private function handleGenerateThumbnailsOperation(array $uriVariables): array
    {
        // Load image entity
        $imageId = $uriVariables['id'] ?? null;
        if (!$imageId) {
            throw new InvalidArgumentException('Image ID is required');
        }

        $image = $this->entityManager->getRepository(Image::class)->find($imageId);
        if (!$image) {
            throw new RuntimeException('Image not found');
        }

        // Generate all thumbnails using ImageService
        // This will use auto-crop for all thumbnails
        $thumbnails = $this->imageService->generateAllThumbnails($image);

        // Return array of thumbnails (will be serialized with 'thumbnail:read' group)
        return $thumbnails;
    }
}
