<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\ShortLink;
use App\Service\ShortCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * @implements ProcessorInterface<ShortLink>
 */
final class ShortLinkProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShortCodeGenerator $shortCodeGenerator,
        private readonly Security $security
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ShortLink
    {
        // Handle DELETE operation
        if ($operation instanceof DeleteOperationInterface) {
            if ($data instanceof ShortLink) {
                $this->entityManager->remove($data);
                $this->entityManager->flush();
            }

            return null;
        }

        // For POST operations
        if ($data instanceof ShortLink) {
            $isNew = !$data->getId();

            if ($isNew) {
                // If no custom code provided, generate one
                if (!$data->getCode()) {
                    $code = $this->shortCodeGenerator->generate();
                    $data->setCode($code);
                } else {
                    // Validate custom code
                    $code = $data->getCode();

                    if (!$this->shortCodeGenerator->isValidCode($code)) {
                        throw new BadRequestHttpException('Invalid short code format. Use only letters, numbers, dashes and underscores.');
                    }

                    if (!$this->shortCodeGenerator->isCodeAvailable($code)) {
                        throw new ConflictHttpException('This short code is already in use.');
                    }
                }

                // Set created by user
                $user = $this->security->getUser();
                if ($user) {
                    $data->setCreatedBy($user);
                }

                $this->entityManager->persist($data);
            }

            $this->entityManager->flush();

            return $data;
        }

        return null;
    }
}
