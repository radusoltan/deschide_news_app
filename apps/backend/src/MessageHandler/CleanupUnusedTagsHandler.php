<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\CleanupUnusedTagsMessage;
use App\Service\TagService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for CleanupUnusedTagsMessage.
 *
 * Processes the cleanup of unused tags asynchronously.
 */
#[AsMessageHandler]
final class CleanupUnusedTagsHandler
{
    public function __construct(
        private readonly TagService $tagService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(CleanupUnusedTagsMessage $message): void
    {
        $daysOld = $message->getDaysOld();
        $dryRun = $message->isDryRun();

        $this->logger->info('Starting cleanup of unused tags', [
            'daysOld' => $daysOld,
            'dryRun' => $dryRun,
        ]);

        try {
            $deletedCount = $this->tagService->cleanupUnusedTags($daysOld, $dryRun);

            if ($dryRun) {
                $this->logger->info('Dry run: Would delete {count} unused tags', [
                    'count' => $deletedCount,
                    'daysOld' => $daysOld,
                ]);
            } else {
                $this->logger->info('Successfully deleted {count} unused tags', [
                    'count' => $deletedCount,
                    'daysOld' => $daysOld,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to cleanup unused tags', [
                'error' => $e->getMessage(),
                'daysOld' => $daysOld,
            ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }
}
