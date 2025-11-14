<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ArticleImage;
use App\Repository\ArticleImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * @implements ProcessorInterface<ArticleImage>
 */
final class ArticleImageProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ArticleImageRepository $articleImageRepository
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ArticleImage
    {
        // Handle DELETE operation
        if ($operation instanceof DeleteOperationInterface) {
            if ($data instanceof ArticleImage) {
                $this->entityManager->remove($data);
                $this->entityManager->flush();
            }

            return null;
        }

        // For POST and PUT operations
        if ($data instanceof ArticleImage) {
            $isUpdate = isset($uriVariables['id']);

            if (!$isUpdate) {
                // CREATE: Validate required fields
                if (!$data->getArticle() || !$data->getImage()) {
                    throw new InvalidArgumentException('Article and Image are required');
                }

                // CREATE: Validate unique constraint
                if ($this->articleImageRepository->isImageAttachedToArticle($data->getArticle(), $data->getImage())) {
                    throw new LogicException('Image is already attached to this article');
                }

                // If setting as featured, unfeatured others
                if ($data->isFeatured()) {
                    $this->unfeaturedAllForArticle($data->getArticle());
                }

                // Auto-position if not provided
                if ($data->getPosition() === 0) {
                    $count = \count($this->articleImageRepository->findByArticleOrdered($data->getArticle()));
                    $data->setPosition($count);
                }

                $this->entityManager->persist($data);
                $this->entityManager->flush();
            } else {
                // UPDATE: Load existing entity
                $existingEntity = $this->entityManager->getRepository(ArticleImage::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new RuntimeException('ArticleImage not found');
                }

                // Update position if provided (non-zero or explicitly set)
                if ($data->getPosition() > 0 || ($context['previous_data'] ?? null)?->getPosition() !== $data->getPosition()) {
                    $existingEntity->setPosition($data->getPosition());
                }

                // If setting as featured, unfeatured others
                if ($data->isFeatured() && !$existingEntity->isFeatured()) {
                    $this->unfeaturedAllForArticle($existingEntity->getArticle());
                    $existingEntity->setIsFeatured(true);
                } elseif (!$data->isFeatured() && $existingEntity->isFeatured()) {
                    $existingEntity->setIsFeatured(false);
                } elseif ($data->isFeatured()) {
                    $existingEntity->setIsFeatured(true);
                }

                $this->entityManager->flush();

                return $existingEntity;
            }

            return $data;
        }

        return null;
    }

    private function unfeaturedAllForArticle($article): void
    {
        $articleImages = $this->articleImageRepository->findByArticleOrdered($article);

        foreach ($articleImages as $ai) {
            $ai->setIsFeatured(false);
        }
    }
}
