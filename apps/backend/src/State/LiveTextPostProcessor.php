<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\LiveText\PostCreatedEventDto;
use App\Dto\LiveText\PostDeletedEventDto;
use App\Dto\LiveText\PostUpdatedEventDto;
use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Enum\LiveTextStatus;
use App\Service\LiveTextNotificationService;
use App\Service\SocialMediaService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<LiveTextPost>
 */
final class LiveTextPostProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LiveTextNotificationService $notificationService,
        private readonly SocialMediaService $socialMediaService
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?LiveTextPost
    {
        // Handle DELETE operation
        if ($operation instanceof DeleteOperationInterface) {
            if ($data instanceof LiveTextPost) {
                // Store data before deletion for event
                $liveTextId = $data->getLiveText()->getId();
                $postId = $data->getId();

                $this->entityManager->remove($data);
                $this->entityManager->flush();

                // Publish post.deleted event
                $event = new PostDeletedEventDto(
                    liveTextId: $liveTextId,
                    postId: $postId,
                    timestamp: new DateTime()
                );
                $this->notificationService->publishEvent($event);
            }

            return null;
        }

        // For POST and PUT operations
        if ($data instanceof LiveTextPost) {
            // Check if this is an update (PUT) by looking at URI variables
            $isUpdate = isset($uriVariables['id']);

            if ($isUpdate) {
                // Load existing entity for updates
                $existingEntity = $this->entityManager->getRepository(LiveTextPost::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new RuntimeException('LiveTextPost not found');
                }

                // Business logic validation
                $this->validatePost($data);
                $this->validateLiveTextStatus($data->getLiveText());

                // Update fields from deserialized data
                $existingEntity->setContent($data->getContent());
                $existingEntity->setContentHtml($data->getContentHtml());
                $existingEntity->setIsKeyPoint($data->isKeyPoint());
                $existingEntity->setPosition($data->getPosition());

                if ($data->getPublishedAt()) {
                    $existingEntity->setPublishedAt($data->getPublishedAt());
                }

                // Update LiveText if provided
                if ($data->getLiveText()) {
                    $this->validateLiveTextStatus($data->getLiveText());
                    $existingEntity->setLiveText($data->getLiveText());
                }

                $this->entityManager->persist($existingEntity);
                $this->entityManager->flush();

                // Publish post.updated event
                $event = PostUpdatedEventDto::fromEntity($existingEntity);
                $this->notificationService->publishEvent($event);

                return $existingEntity;
            }
            // New entity (POST)
            $this->validatePost($data);
            $this->validateLiveTextStatus($data->getLiveText());

            // Set published time if not set
            if (!$data->getPublishedAt()) {
                $data->setPublishedAt(new DateTime());
            }

            // Auto-set position if not set
            if ($data->getPosition() === 0) {
                $maxPosition = $this->getMaxPositionForLiveText($data->getLiveText());
                $data->setPosition($maxPosition + 1);
            }

            $this->entityManager->persist($data);
            $this->entityManager->flush();

            // Publish post.created event
            $event = PostCreatedEventDto::fromEntity($data);
            $this->notificationService->publishEvent($event);

            // Auto-post to social media if this is a key point
            if ($data->isKeyPoint()) {
                $this->socialMediaService->postImportantUpdate($data);
            }

            return $data;

        }

        return null;
    }

    /**
     * Validate post content.
     */
    private function validatePost(LiveTextPost $post): void
    {
        if (!$post->getContent() || trim($post->getContent()) === '') {
            throw new BadRequestHttpException('Post content cannot be empty.');
        }

        if (\strlen($post->getContent()) > 10000) {
            throw new BadRequestHttpException('Post content cannot exceed 10000 characters.');
        }

        if (!$post->getLiveText()) {
            throw new BadRequestHttpException('Post must be associated with a LiveText.');
        }

        if (!$post->getAuthor()) {
            throw new BadRequestHttpException('Post must have an author.');
        }
    }

    /**
     * Validate that LiveText allows posting.
     */
    private function validateLiveTextStatus(LiveText $liveText): void
    {
        // Don't allow posting to ENDED LiveTexts
        if ($liveText->getStatus() === LiveTextStatus::ENDED) {
            throw new BadRequestHttpException('Cannot create or update posts in an ended LiveText.');
        }

        // Optionally: only allow posting to LIVE or PAUSED LiveTexts
        // Uncomment if you want stricter validation
        // if (!in_array($liveText->getStatus(), [LiveTextStatus::LIVE, LiveTextStatus::PAUSED], true)) {
        //     throw new BadRequestHttpException('Can only post to LIVE or PAUSED LiveTexts.');
        // }
    }

    /**
     * Get max position for a LiveText to auto-increment.
     */
    private function getMaxPositionForLiveText(LiveText $liveText): int
    {
        $result = $this->entityManager->createQueryBuilder()
            ->select('MAX(p.position)')
            ->from(LiveTextPost::class, 'p')
            ->where('p.liveText = :liveText')
            ->setParameter('liveText', $liveText)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : 0;
    }
}
