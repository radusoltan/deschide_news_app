<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CheckOrphanedTagsMessage;
use App\Repository\TagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for CheckOrphanedTagsMessage.
 *
 * Checks for orphaned tags (usageCount = 0) and removes them.
 * Typically triggered after article deletion.
 */
#[AsMessageHandler]
final class CheckOrphanedTagsHandler
{
    public function __construct(
        private readonly TagRepository $tagRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(CheckOrphanedTagsMessage $message): void
    {
        $tagIds = $message->getTagIds();

        if (!empty($tagIds)) {
            // Check specific tags
            $this->logger->info('Checking {count} tags for orphaned status', [
                'count' => \count($tagIds),
                'tagIds' => $tagIds,
            ]);

            $deletedCount = 0;

            foreach ($tagIds as $tagId) {
                $tag = $this->tagRepository->find($tagId);

                if (!$tag) {
                    continue;
                }

                // Only delete if truly orphaned (no articles)
                if ($tag->getUsageCount() === 0 && $tag->getArticles()->count() === 0) {
                    $this->logger->info('Removing orphaned tag {tagId}: {name}', [
                        'tagId' => $tag->getId(),
                        'name' => $tag->getName(),
                    ]);

                    $this->entityManager->remove($tag);
                    ++$deletedCount;
                }
            }

            if ($deletedCount > 0) {
                $this->entityManager->flush();

                $this->logger->info('Removed {count} orphaned tags', [
                    'count' => $deletedCount,
                ]);
            }
        } else {
            // Check all tags with usageCount = 0
            $this->logger->info('Checking all tags with usageCount = 0');

            $unusedTags = $this->tagRepository->createQueryBuilder('t')
                ->where('t.usageCount = 0')
                ->getQuery()
                ->getResult();

            $deletedCount = 0;

            foreach ($unusedTags as $tag) {
                // Double-check with actual article count
                if ($tag->getArticles()->count() === 0) {
                    $this->logger->debug('Removing orphaned tag {tagId}: {name}', [
                        'tagId' => $tag->getId(),
                        'name' => $tag->getName(),
                    ]);

                    $this->entityManager->remove($tag);
                    ++$deletedCount;
                }
            }

            if ($deletedCount > 0) {
                $this->entityManager->flush();

                $this->logger->info('Removed {count} orphaned tags', [
                    'count' => $deletedCount,
                ]);
            } else {
                $this->logger->info('No orphaned tags found');
            }
        }
    }
}
