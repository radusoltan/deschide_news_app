<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\PressRelease;
use App\Enum\PressReleaseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PressReleaseRejectProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PressRelease
    {
        if (!$data instanceof PressRelease) {
            throw new BadRequestHttpException('Invalid data');
        }

        if ($data->getStatus() !== PressReleaseStatus::PENDING) {
            throw new BadRequestHttpException('Only pending press releases can be rejected');
        }

        $data->setStatus(PressReleaseStatus::REJECTED);
        $data->setProcessedAt(new \DateTimeImmutable());

        $user = $this->security->getUser();
        if ($user !== null) {
            $data->setProcessedBy($user);
        }

        $this->em->flush();

        $this->logger->info('PressRelease rejected', [
            'pressReleaseId' => $data->getId(),
            'sourceType' => $data->getSourceType()->value,
            'rejectionReason' => $data->getRejectionReason(),
        ]);

        return $data;
    }
}
