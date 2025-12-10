<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RecalculateTagCountsMessage;
use App\Repository\TagRepository;
use App\Service\TagService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for RecalculateTagCountsMessage.
 *
 * Recalculates usage counts for tags to ensure data consistency.
 */
#[AsMessageHandler]
final class RecalculateTagCountsHandler
{
    public function __construct(
        private readonly TagService $tagService,
        private readonly TagRepository $tagRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(RecalculateTagCountsMessage $message): void
    {
        $tagId = $message->getTagId();

        if ($tagId !== null) {
            // Recalculate for specific tag
            $this->logger->info('Recalculating usage count for tag {tagId}', [
                'tagId' => $tagId,
            ]);

            $tag = $this->tagRepository->find($tagId);
            if (!$tag) {
                $this->logger->warning('Tag {tagId} not found for recalculation', [
                    'tagId' => $tagId,
                ]);

                return;
            }

            $actualCount = $tag->getArticles()->count();
            $oldCount = $tag->getUsageCount();

            $tag->setUsageCount($actualCount);

            $this->logger->info('Updated usage count for tag {tagId}', [
                'tagId' => $tagId,
                'oldCount' => $oldCount,
                'newCount' => $actualCount,
            ]);
        } else {
            // Recalculate for all tags
            $this->logger->info('Starting recalculation of all tag usage counts');

            try {
                $updatedCount = $this->tagService->recalculateUsageCounts();

                $this->logger->info('Successfully recalculated usage counts for {count} tags', [
                    'count' => $updatedCount,
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to recalculate tag counts', [
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        }
    }
}
