<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Thumbnail;
use App\Repository\ThumbnailRepository;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * @implements ProcessorInterface<Thumbnail>
 */
final class ThumbnailProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ThumbnailRepository $thumbnailRepository,
        private readonly ImageService $imageService
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Thumbnail
    {
        // Handle DELETE operation
        if ($operation instanceof DeleteOperationInterface) {
            if ($data instanceof Thumbnail) {
                $this->entityManager->remove($data);
                $this->entityManager->flush();
            }

            return null;
        }

        // Handle custom crop operation (POST /thumbnails/{id}/crop)
        if ($operation->getUriTemplate() === '/thumbnails/{id}/crop') {
            if (!isset($uriVariables['id'])) {
                throw new InvalidArgumentException('Thumbnail ID is required');
            }

            // Load existing thumbnail
            $thumbnail = $this->entityManager->getRepository(Thumbnail::class)->find($uriVariables['id']);

            if (!$thumbnail instanceof Thumbnail) {
                throw new RuntimeException('Thumbnail not found');
            }

            // Get crop data from request
            $cropData = $data->getCropData();

            if (!$cropData) {
                throw new InvalidArgumentException('Crop data is required');
            }

            // Apply crop using ImageService
            return $this->imageService->applyCrop($thumbnail, $cropData);
        }

        // For POST and PUT operations
        if ($data instanceof Thumbnail) {
            $isUpdate = isset($uriVariables['id']);

            if (!$isUpdate) {
                // CREATE: Validate required fields
                if (!$data->getImage()) {
                    throw new InvalidArgumentException('Image is required');
                }

                // CREATE: Validate unique constraint (image + profile)
                if ($data->getProfile() && $this->thumbnailRepository->existsForImageAndProfile($data->getImage(), $data->getProfile())) {
                    throw new LogicException('Thumbnail already exists for this image and profile');
                }

                $this->entityManager->persist($data);
                $this->entityManager->flush();
            } else {
                // UPDATE: Load existing entity
                $existingEntity = $this->entityManager->getRepository(Thumbnail::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new RuntimeException('Thumbnail not found');
                }

                // Update only width, height, size, and cropData
                $existingEntity->setWidth($data->getWidth());
                $existingEntity->setHeight($data->getHeight());
                $existingEntity->setSize($data->getSize());

                if ($data->getCropData() !== null) {
                    $existingEntity->setCropData($data->getCropData());
                }

                $this->entityManager->flush();

                return $existingEntity;
            }

            return $data;
        }

        return null;
    }
}
