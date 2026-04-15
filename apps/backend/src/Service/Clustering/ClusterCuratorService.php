<?php

declare(strict_types=1);

namespace App\Service\Clustering;

use App\Entity\CurationSuggestion;
use App\Entity\StoryCluster;
use App\Enum\CurationSuggestionType;
use App\Enum\StoryClusterStatus;
use App\Repository\CurationSuggestionRepository;
use App\Repository\StoryClusterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Analyses story clusters and generates curation suggestions:
 * - MERGE: similar clusters that should be combined
 * - ARCHIVE: stale clusters with no recent activity
 * - RETAG: clusters missing topic classification
 */
class ClusterCuratorService
{
    public function __construct(
        private readonly StoryClusterRepository $clusterRepository,
        private readonly CurationSuggestionRepository $suggestionRepository,
        private readonly ElasticsearchClusterFinder $esFinder,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Run all curation checks and persist suggestions.
     *
     * @return CurationSuggestion[]
     */
    public function curate(): array
    {
        $suggestions = [];

        $suggestions = array_merge($suggestions, $this->detectMergeCandidates());
        $suggestions = array_merge($suggestions, $this->detectStaleClusters());

        foreach ($suggestions as $suggestion) {
            $this->em->persist($suggestion);
        }

        if ($suggestions !== []) {
            $this->em->flush();
        }

        $this->logger->info('ClusterCuratorService: generated {count} suggestions', [
            'count' => \count($suggestions),
            'merge' => \count(array_filter($suggestions, fn(CurationSuggestion $s) => $s->getType() === CurationSuggestionType::MERGE)),
            'archive' => \count(array_filter($suggestions, fn(CurationSuggestion $s) => $s->getType() === CurationSuggestionType::ARCHIVE)),
        ]);

        return $suggestions;
    }

    /**
     * Find clusters with similar summaries that might be about the same story.
     *
     * @return CurationSuggestion[]
     */
    private function detectMergeCandidates(): array
    {
        $suggestions = [];

        if (!$this->esFinder->isEnabled()) {
            $this->logger->debug('ClusterCuratorService: ES not available, skipping merge detection');

            return [];
        }

        $since = new \DateTimeImmutable('-30 days');
        $clusters = $this->clusterRepository->findActiveClustersInWindow($since);

        // Index by ID for quick lookup
        $clusterById = [];
        foreach ($clusters as $cluster) {
            $clusterById[$cluster->getId()] = $cluster;
        }

        $processed = [];

        foreach ($clusters as $cluster) {
            if ($cluster->getSummaryShort() === null) {
                continue;
            }

            $text = $cluster->getPrimaryHeadline() . ' ' . $cluster->getSummaryShort();
            $similar = $this->esFinder->findSimilar($text, $text, 0.80);

            foreach ($similar as $hit) {
                $prId = $hit['pressReleaseId'];

                // Find which cluster contains this PR
                foreach ($clusterById as $otherCluster) {
                    if ($otherCluster->getId() === $cluster->getId()) {
                        continue;
                    }

                    $pairKey = min($cluster->getId(), $otherCluster->getId()) . '-' . max($cluster->getId(), $otherCluster->getId());
                    if (isset($processed[$pairKey])) {
                        continue;
                    }

                    // Check if this cluster has the PR
                    $found = false;
                    foreach ($otherCluster->getPressReleases() as $pr) {
                        if ($pr->getId() === $prId) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        continue;
                    }

                    $processed[$pairKey] = true;
                    $clusterIds = [$cluster->getId(), $otherCluster->getId()];
                    sort($clusterIds);

                    // Skip if we already suggested this merge recently
                    if ($this->suggestionRepository->existsRecentForClusters(CurationSuggestionType::MERGE, $clusterIds)) {
                        continue;
                    }

                    $targetId = $cluster->getImportanceScore() >= $otherCluster->getImportanceScore()
                        ? $cluster->getId()
                        : $otherCluster->getId();

                    $suggestion = new CurationSuggestion();
                    $suggestion->setType(CurationSuggestionType::MERGE);
                    $suggestion->setClusterIds($clusterIds);
                    $suggestion->setTargetClusterId($targetId);
                    $suggestion->setConfidence($hit['score']);
                    $suggestion->setReason(sprintf(
                        'Aceste clustere par să acopere același subiect (similaritate: %.0f%%). Recomandăm fuzionarea în clusterul cu scor mai mare.',
                        $hit['score'] * 100,
                    ));
                    $suggestion->setClusterHeadlines([
                        $cluster->getPrimaryHeadline(),
                        $otherCluster->getPrimaryHeadline(),
                    ]);

                    $suggestions[] = $suggestion;
                }
            }
        }

        return $suggestions;
    }

    /**
     * Find clusters that have had no new press releases in 14+ days and low relevance.
     *
     * @return CurationSuggestion[]
     */
    private function detectStaleClusters(): array
    {
        $suggestions = [];
        $cutoff = new \DateTimeImmutable('-14 days');

        $staleClusters = $this->em->createQuery(
            'SELECT sc FROM App\Entity\StoryCluster sc
             WHERE sc.lastUpdatedAt < :cutoff
             AND sc.importanceScore < 0.50
             AND sc.status NOT IN (:excludeStatuses)'
        )
            ->setParameter('cutoff', $cutoff)
            ->setParameter('excludeStatuses', [
                StoryClusterStatus::REJECTED,
                StoryClusterStatus::ARCHIVED,
                StoryClusterStatus::PROMOTED,
            ])
            ->getResult();

        foreach ($staleClusters as $cluster) {
            $clusterIds = [$cluster->getId()];

            if ($this->suggestionRepository->existsRecentForClusters(CurationSuggestionType::ARCHIVE, $clusterIds)) {
                continue;
            }

            $suggestion = new CurationSuggestion();
            $suggestion->setType(CurationSuggestionType::ARCHIVE);
            $suggestion->setClusterIds($clusterIds);
            $suggestion->setConfidence(0.80);
            $suggestion->setReason(sprintf(
                'Cluster inactiv de %d zile (ultimul update: %s) cu scor de importanță scăzut (%.2f). Se recomandă arhivarea.',
                (new \DateTimeImmutable())->diff($cluster->getLastUpdatedAt())->days,
                $cluster->getLastUpdatedAt()->format('d.m.Y'),
                $cluster->getImportanceScore(),
            ));
            $suggestion->setClusterHeadlines([$cluster->getPrimaryHeadline()]);

            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }
}
