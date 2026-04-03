<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\DuplicateCheckResult;
use App\Entity\Article;
use App\Repository\PressReleaseRepository;
use Doctrine\ORM\EntityManagerInterface;

class ContentDeduplicator
{
    public function __construct(
        private readonly PressReleaseRepository $pressReleaseRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function isDuplicate(string $contentHash): DuplicateCheckResult
    {
        // Check PressRelease table first
        $pr = $this->pressReleaseRepository->findByContentHash($contentHash);
        if ($pr !== null) {
            return new DuplicateCheckResult(
                isDuplicate: true,
                existingEntityType: 'PressRelease',
                existingEntityId: $pr->getId(),
                existingSourceType: $pr->getSourceType(),
            );
        }

        // Check Article table
        $article = $this->em->getRepository(Article::class)->findOneBy(['contentHash' => $contentHash]);
        if ($article !== null) {
            return new DuplicateCheckResult(
                isDuplicate: true,
                existingEntityType: 'Article',
                existingEntityId: $article->getId(),
            );
        }

        return new DuplicateCheckResult(isDuplicate: false);
    }
}
