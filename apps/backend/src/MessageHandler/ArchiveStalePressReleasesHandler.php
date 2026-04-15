<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Enum\PressReleaseStatus;
use App\Message\ArchiveStalePressReleasesMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ArchiveStalePressReleasesHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ArchiveStalePressReleasesMessage $message): void
    {
        $cutoff = new \DateTimeImmutable("-{$message->days} days");

        $updated = $this->em->createQuery(
            'UPDATE App\Entity\PressRelease pr
             SET pr.status = :archived
             WHERE pr.status = :rejected AND pr.createdAt < :cutoff'
        )
            ->setParameter('archived', PressReleaseStatus::ARCHIVED)
            ->setParameter('rejected', PressReleaseStatus::REJECTED)
            ->setParameter('cutoff', $cutoff)
            ->execute();

        $this->logger->info('ArchiveStalePressReleasesHandler: archived {count} rejected PRs older than {days} days', [
            'count' => $updated,
            'days' => $message->days,
        ]);
    }
}
