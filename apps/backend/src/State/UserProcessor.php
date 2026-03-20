<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<User>
 */
final class UserProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?User
    {
        if (!$data instanceof User) {
            return null;
        }

        // Handle DELETE operation
        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($data, $uriVariables);
        }

        // Handle CREATE / UPDATE
        return $this->handleCreateOrUpdate($data, $uriVariables);
    }

    private function handleDelete(User $data, array $uriVariables): null
    {
        $currentUser = $this->security->getUser();

        // Prevent self-deletion
        if ($currentUser instanceof User && $currentUser->getId() === (int) ($uriVariables['id'] ?? 0)) {
            throw new AccessDeniedHttpException('Cannot delete your own account.');
        }

        $managedEntity = $this->entityManager->getRepository(User::class)->find($uriVariables['id'] ?? $data->getId());

        if ($managedEntity) {
            $this->entityManager->remove($managedEntity);
            $this->entityManager->flush();
        }

        return null;
    }

    private function handleCreateOrUpdate(User $data, array $uriVariables): User
    {
        $isUpdate = isset($uriVariables['id']);

        if ($isUpdate) {
            // Load existing entity for update
            $existingEntity = $this->entityManager->getRepository(User::class)->find($uriVariables['id']);

            if (!$existingEntity) {
                throw new \RuntimeException('User not found');
            }

            // Update fields from deserialized data
            if ($data->getUsername() !== null) {
                $existingEntity->setUsername($data->getUsername());
            }
            if ($data->getEmail() !== null) {
                $existingEntity->setEmail($data->getEmail());
            }
            if ($data->getFirstName() !== null || $data->getFirstName() === null) {
                $existingEntity->setFirstName($data->getFirstName());
            }
            if ($data->getLastName() !== null || $data->getLastName() === null) {
                $existingEntity->setLastName($data->getLastName());
            }
            if (!empty($data->getRoles())) {
                $existingEntity->setRoles($data->getRoles());
            }
            $existingEntity->setIsActive($data->isActive());

            // Hash password if plainPassword is provided
            if ($data->getPlainPassword()) {
                $hashedPassword = $this->passwordHasher->hashPassword($existingEntity, $data->getPlainPassword());
                $existingEntity->setPassword($hashedPassword);
            }

            $this->entityManager->flush();

            return $existingEntity;
        }

        // CREATE: Hash password (required for new users)
        if ($data->getPlainPassword()) {
            $hashedPassword = $this->passwordHasher->hashPassword($data, $data->getPlainPassword());
            $data->setPassword($hashedPassword);
            $data->eraseCredentials();
        }

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
