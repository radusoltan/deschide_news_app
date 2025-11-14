<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\LiveTextCollaborator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @implements ProcessorInterface<LiveTextCollaborator>
 */
final class LiveTextCollaboratorProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?LiveTextCollaborator
    {
        // Handle DELETE operation
        if ($operation instanceof DeleteOperationInterface) {
            if ($data instanceof LiveTextCollaborator) {
                $this->entityManager->remove($data);
                $this->entityManager->flush();
            }

            return null;
        }

        // For POST operations (no PUT for collaborators)
        if ($data instanceof LiveTextCollaborator) {
            // Business logic validation
            $this->validateCollaborator($data);

            // Check for duplicate (same user + same liveText)
            $this->checkDuplicate($data);

            // Ensure valid role
            if (!\in_array($data->getRole(), ['editor', 'contributor'], true)) {
                $data->setRole('contributor');
            }

            $this->entityManager->persist($data);
            $this->entityManager->flush();

            return $data;
        }

        return null;
    }

    /**
     * Validate collaborator data.
     */
    private function validateCollaborator(LiveTextCollaborator $collaborator): void
    {
        if (!$collaborator->getLiveText()) {
            throw new BadRequestHttpException('Collaborator must be associated with a LiveText.');
        }

        if (!$collaborator->getUser()) {
            throw new BadRequestHttpException('Collaborator must have a User.');
        }

        // Don't allow adding the LiveText author as a collaborator
        if ($collaborator->getUser() === $collaborator->getLiveText()->getAuthor()) {
            throw new BadRequestHttpException('The LiveText author is already a collaborator by default.');
        }
    }

    /**
     * Check if collaborator already exists.
     */
    private function checkDuplicate(LiveTextCollaborator $collaborator): void
    {
        $existing = $this->entityManager->getRepository(LiveTextCollaborator::class)
            ->findOneBy([
                'liveText' => $collaborator->getLiveText(),
                'user' => $collaborator->getUser(),
            ]);

        if ($existing) {
            throw new BadRequestHttpException('This user is already a collaborator on this LiveText.');
        }
    }
}
