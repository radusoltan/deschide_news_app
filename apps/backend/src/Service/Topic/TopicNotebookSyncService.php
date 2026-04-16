<?php

declare(strict_types=1);

namespace App\Service\Topic;

use App\Entity\Topic;
use App\Enum\TopicStatus;
use App\Repository\TopicRepository;
use App\Service\NotebookLM\NotebookLMService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class TopicNotebookSyncService
{
    public function __construct(
        private readonly NotebookLMService $notebookLM,
        private readonly TopicMarkdownExporter $exporter,
        private readonly TopicRepository $topicRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Sync a single topic's sources to its NotebookLM notebook.
     *
     * @return array{sources_added: int, notebook_id: string|null, skipped: bool}
     */
    public function syncTopic(
        Topic $topic,
        DateRange $range,
        int $maxSources = 300,
        bool $dryRun = false,
    ): array {
        $result = ['sources_added' => 0, 'notebook_id' => null, 'skipped' => false];

        if (!$this->notebookLM->isAvailable()) {
            $this->logger->info('TopicNotebookSync: NotebookLM not available, skipping');
            $result['skipped'] = true;

            return $result;
        }

        // Ensure notebook exists (creates if needed)
        $notebookId = $this->notebookLM->ensureNotebookForTopic($topic);
        if ($notebookId === null) {
            $this->logger->warning('TopicNotebookSync: could not provision notebook', [
                'topicId' => $topic->getId(),
            ]);
            $result['skipped'] = true;

            return $result;
        }
        $result['notebook_id'] = $notebookId;

        // Export individual sources
        $sources = $this->exporter->exportSources($topic, $range, $maxSources);

        if ($sources === []) {
            $this->logger->info('TopicNotebookSync: no sources to sync', [
                'topicId' => $topic->getId(),
            ]);

            return $result;
        }

        $this->logger->info('TopicNotebookSync: starting sync', [
            'topicId' => $topic->getId(),
            'sourcesFound' => \count($sources),
            'dryRun' => $dryRun,
        ]);

        foreach ($sources as $source) {
            if ($topic->getNotebookSourceCount() >= $maxSources) {
                $this->logger->warning('TopicNotebookSync: max sources reached', [
                    'topicId' => $topic->getId(),
                    'limit' => $maxSources,
                ]);
                break;
            }

            if ($dryRun) {
                $result['sources_added']++;
                continue;
            }

            $added = $this->notebookLM->addTextSource($notebookId, $source['title'], $source['content']);
            if ($added) {
                $topic->setNotebookSourceCount($topic->getNotebookSourceCount() + 1);
                $result['sources_added']++;
            }
        }

        if (!$dryRun) {
            $topic->setNotebookLastSyncedAt(new \DateTimeImmutable());
            $this->em->flush();
        }

        $this->logger->info('TopicNotebookSync: sync complete', [
            'topicId' => $topic->getId(),
            'sourcesAdded' => $result['sources_added'],
            'totalSources' => $topic->getNotebookSourceCount(),
        ]);

        return $result;
    }

    /**
     * Sync all active topics.
     *
     * @return array{topics_synced: int, total_sources: int, errors: int}
     */
    public function syncAllActiveTopics(
        DateRange $range,
        int $maxSources = 300,
        bool $dryRun = false,
    ): array {
        $summary = ['topics_synced' => 0, 'total_sources' => 0, 'errors' => 0];

        $topics = $this->topicRepository->findBy([
            'isActive' => true,
            'status' => TopicStatus::ACTIVE,
        ]);

        foreach ($topics as $topic) {
            try {
                $result = $this->syncTopic($topic, $range, $maxSources, $dryRun);
                if (!$result['skipped']) {
                    $summary['topics_synced']++;
                    $summary['total_sources'] += $result['sources_added'];
                }
            } catch (\Throwable $e) {
                $summary['errors']++;
                $this->logger->error('TopicNotebookSync: error syncing topic', [
                    'topicId' => $topic->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }
}
