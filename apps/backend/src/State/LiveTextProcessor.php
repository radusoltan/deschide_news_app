<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\LiveText\StatusChangedEventDto;
use App\Entity\LiveText;
use App\Enum\LiveTextStatus;
use App\Service\LiveTextNotificationService;
use App\Service\SocialMediaService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<LiveText>
 */
final class LiveTextProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly LiveTextNotificationService $notificationService,
        private readonly SocialMediaService $socialMediaService
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?LiveText
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
            if ($data instanceof LiveText) {
                $this->entityManager->remove($data);
                $this->entityManager->flush();
            }

            return null;
        }

        // For POST and PUT operations
        if ($data instanceof LiveText) {
            // Check if this is an update (PUT) by looking at URI variables
            $isUpdate = isset($uriVariables['id']);

            if ($isUpdate) {
                // Load existing entity for updates
                $existingEntity = $this->entityManager->getRepository(LiveText::class)->find($uriVariables['id']);

                if (!$existingEntity) {
                    throw new RuntimeException('LiveText not found');
                }

                // Business logic validation
                $oldStatus = $existingEntity->getStatus();
                $this->validateStatusTransition($existingEntity, $data);
                $this->validateDates($data);

                // Update fields from deserialized data
                $existingEntity->setTitle($data->getTitle());
                $existingEntity->setDescription($data->getDescription());
                $existingEntity->setStatus($data->getStatus());
                $existingEntity->setStartTime($data->getStartTime());
                $existingEntity->setEndTime($data->getEndTime());

                // Update category if provided
                if ($data->getCategory()) {
                    $existingEntity->setCategory($data->getCategory());
                }

                // Set locale for translatable fields
                $existingEntity->setTranslatableLocale($locale);

                $this->entityManager->persist($existingEntity);
                $this->entityManager->flush();

                // Publish status.changed event if status changed
                if ($oldStatus !== $data->getStatus()) {
                    $event = StatusChangedEventDto::fromEntity($existingEntity);
                    $this->notificationService->publishEvent($event);

                    // Auto-post to social media when going LIVE
                    if ($data->getStatus() === LiveTextStatus::LIVE && $oldStatus !== LiveTextStatus::LIVE) {
                        $this->socialMediaService->postLiveTextStarted($existingEntity);
                    }
                }

                return $existingEntity;
            }
            // New entity (POST)
            $this->validateDates($data);

            // Set locale for translatable fields
            $data->setTranslatableLocale($locale);

            // Ensure default status is DRAFT if not set
            if (!$data->getStatus()) {
                $data->setStatus(LiveTextStatus::DRAFT);
            }

            $this->entityManager->persist($data);
            $this->entityManager->flush();

            return $data;

        }

        return null;
    }

    /**
     * Validate status transitions.
     */
    private function validateStatusTransition(LiveText $existing, LiveText $new): void
    {
        $oldStatus = $existing->getStatus();
        $newStatus = $new->getStatus();

        // Don't allow transition from ENDED back to other statuses
        if ($oldStatus === LiveTextStatus::ENDED && $newStatus !== LiveTextStatus::ENDED) {
            throw new BadRequestHttpException('Cannot change status from ENDED to another status.');
        }

        // If transitioning to ENDED, ensure endTime is set
        if ($newStatus === LiveTextStatus::ENDED && !$new->getEndTime()) {
            $new->setEndTime(new DateTime());
        }

        // If transitioning to LIVE, ensure startTime is set
        if ($newStatus === LiveTextStatus::LIVE && !$new->getStartTime()) {
            $new->setStartTime(new DateTime());
        }
    }

    /**
     * Validate date logic.
     */
    private function validateDates(LiveText $liveText): void
    {
        // If both startTime and endTime are set, ensure endTime is after startTime
        if ($liveText->getStartTime() && $liveText->getEndTime()) {
            if ($liveText->getEndTime() <= $liveText->getStartTime()) {
                throw new BadRequestHttpException('End time must be after start time.');
            }
        }

        // If status is ENDED, endTime should be set
        if ($liveText->getStatus() === LiveTextStatus::ENDED && !$liveText->getEndTime()) {
            $liveText->setEndTime(new DateTime());
        }

        // If status is LIVE, startTime should be set
        if ($liveText->getStatus() === LiveTextStatus::LIVE && !$liveText->getStartTime()) {
            $liveText->setStartTime(new DateTime());
        }
    }
}
