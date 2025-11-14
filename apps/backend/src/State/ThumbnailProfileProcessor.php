<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ThumbnailProfile;
use App\Repository\ThumbnailProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * @implements ProcessorInterface<ThumbnailProfile>
 */
final class ThumbnailProfileProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ThumbnailProfileRepository $thumbnailProfileRepository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ThumbnailProfile
    {
        // Handle DELETE operation
        if ($operation instanceof DeleteOperationInterface) {
            if ($data instanceof ThumbnailProfile) {
                // Check if profile has associated thumbnails
                if ($data->getThumbnails()->count() > 0) {
                    throw new LogicException('Cannot delete profile that has associated thumbnails');
                }

                $this->entityManager->remove($data);
                $this->entityManager->flush();
            }

            return null;
        }

        // For POST and PUT operations
        if ($data instanceof ThumbnailProfile) {
            $isUpdate = isset($uriVariables['id']);

            if (!$isUpdate) {
                // CREATE in default locale (ro)
                $data->setTranslatableLocale('ro');

                // Validate required fields
                if (!$data->getName()) {
                    throw new InvalidArgumentException('Name is required');
                }

                // Validate unique name
                if ($this->thumbnailProfileRepository->findOneByName($data->getName())) {
                    throw new LogicException('Profile with this name already exists');
                }

                // Validate unique dimensions + mode
                if ($this->thumbnailProfileRepository->existsByDimensionsAndMode(
                    $data->getWidth(),
                    $data->getHeight(),
                    $data->getMode()->value
                )) {
                    throw new LogicException('Profile with these dimensions and mode already exists');
                }

                $this->entityManager->persist($data);
                $this->entityManager->flush();
            } else {
                // UPDATE: Load existing entity
                $existingEntity = $this->entityManager->getRepository(ThumbnailProfile::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new RuntimeException('ThumbnailProfile not found');
                }

                // Extract locale from context (set by denormalization)
                $locale = $context['locale'] ?? 'ro';

                // Get translation repository
                /** @var TranslationRepository $translationRepo */
                $translationRepo = $this->entityManager->getRepository('Gedmo\Translatable\Entity\Translation');

                if ($locale === 'ro') {
                    // Update default locale fields directly
                    if ($data->getDisplayName() !== null) {
                        $existingEntity->setDisplayName($data->getDisplayName());
                    }
                    if ($data->getDescription() !== null) {
                        $existingEntity->setDescription($data->getDescription());
                    }
                } else {
                    // Update translations for non-default locale
                    if ($data->getDisplayName() !== null) {
                        $translationRepo->translate($existingEntity, 'displayName', $locale, $data->getDisplayName());
                    }
                    if ($data->getDescription() !== null) {
                        $translationRepo->translate($existingEntity, 'description', $locale, $data->getDescription());
                    }
                }

                // Update non-translatable fields
                if ($data->getWidth() !== null) {
                    // Validate unique dimensions + mode on update
                    if ($this->thumbnailProfileRepository->existsByDimensionsAndMode(
                        $data->getWidth(),
                        $data->getHeight() ?? $existingEntity->getHeight(),
                        $data->getMode()->value,
                        $existingEntity->getId()
                    )) {
                        throw new LogicException('Profile with these dimensions and mode already exists');
                    }
                    $existingEntity->setWidth($data->getWidth());
                }
                if ($data->getHeight() !== null) {
                    $existingEntity->setHeight($data->getHeight());
                }
                if ($data->getAspectRatio() !== null) {
                    $existingEntity->setAspectRatio($data->getAspectRatio());
                }
                $existingEntity->setMode($data->getMode());
                $existingEntity->setQuality($data->getQuality());
                $existingEntity->setIsActive($data->isActive());
                $existingEntity->setCategory($data->getCategory());

                $this->entityManager->flush();

                return $existingEntity;
            }

            return $data;
        }

        return null;
    }
}
