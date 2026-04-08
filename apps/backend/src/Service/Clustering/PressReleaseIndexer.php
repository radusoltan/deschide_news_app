<?php

declare(strict_types=1);

namespace App\Service\Clustering;

use App\Entity\PressRelease;
use Psr\Log\LoggerInterface;

/**
 * Indexes PressRelease entities into the deschide_press_releases ES index.
 * Used by: CLI command (bulk), Doctrine event listener (single), Messenger handler (async).
 */
readonly class PressReleaseIndexer
{
    public function __construct(
        private PressReleaseIndexManager $indexManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Index a single PressRelease into ES.
     */
    public function index(PressRelease $pr): void
    {
        if (!$this->indexManager->isEnabled() || $pr->getId() === null) {
            return;
        }

        $this->indexManager->indexDocument($pr->getId(), $this->buildDocument($pr));
    }

    /**
     * Bulk index multiple PressReleases.
     *
     * @param iterable<PressRelease> $pressReleases
     * @return int Number successfully indexed
     */
    public function bulkIndex(iterable $pressReleases, int $batchSize = 200): int
    {
        if (!$this->indexManager->isEnabled()) {
            return 0;
        }

        $batch = [];
        $totalIndexed = 0;

        foreach ($pressReleases as $pr) {
            if ($pr->getId() === null) {
                continue;
            }

            $batch[$pr->getId()] = $this->buildDocument($pr);

            if (\count($batch) >= $batchSize) {
                $totalIndexed += $this->indexManager->bulkIndex($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $totalIndexed += $this->indexManager->bulkIndex($batch);
        }

        return $totalIndexed;
    }

    /**
     * Build the ES document for a PressRelease.
     *
     * @return array<string, mixed>
     */
    private function buildDocument(PressRelease $pr): array
    {
        return [
            'press_release_id' => $pr->getId(),
            'title' => $pr->getTitle(),
            'content' => mb_substr(strip_tags($pr->getContent()), 0, 10000),
            'lead' => $pr->getLead() ?? '',
            'source_name' => $pr->getSourceName(),
            'source_hostname' => $pr->getSourceHostname(),
            'source_type' => $pr->getSourceType()->value,
            'category_slug' => $pr->getCategorySlug(),
            'detected_language' => $pr->getDetectedLanguage(),
            'original_language' => $pr->getOriginalLanguage(),
            'created_at' => $pr->getCreatedAt()->format('c'),
            'received_at' => $pr->getReceivedAt()->format('c'),
        ];
    }
}
