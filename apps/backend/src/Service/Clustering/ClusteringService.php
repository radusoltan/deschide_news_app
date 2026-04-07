<?php

declare(strict_types=1);

namespace App\Service\Clustering;

use App\Entity\PressRelease;
use App\Entity\StoryCluster;
use App\Repository\StoryClusterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Groups PressReleases into StoryCluster entities based on content similarity.
 *
 * Algorithm:
 * 1. Find PressReleases not yet assigned to any cluster
 * 2. For each, search ES MLT against existing clustered articles
 * 3. Temporal validation: articles must be within 48h of each other
 * 4. If match → add to existing cluster
 * 5. If no match → create new StoryCluster
 */
class ClusteringService
{
    private const SIMILARITY_THRESHOLD = 0.75;
    private const TEMPORAL_WINDOW_HOURS = 48;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StoryClusterRepository $clusterRepository,
        private readonly ElasticsearchClusterFinder $clusterFinder,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Cluster all unassigned PressReleases within the given time window.
     *
     * @return int Number of PressReleases processed
     */
    public function clusterNewPressReleases(\DateTimeInterface $since = null, bool $dryRun = false): int
    {
        $since ??= new \DateTimeImmutable('-24 hours');

        $unclusteredIds = $this->clusterRepository->findUnclusteredPressReleaseIds($since);

        if ($unclusteredIds === []) {
            $this->logger->info('ClusteringService: no unclustered PressReleases found');
            return 0;
        }

        $this->logger->info('ClusteringService: processing {count} unclustered PressReleases', [
            'count' => \count($unclusteredIds),
        ]);

        $processed = 0;

        foreach ($unclusteredIds as $prId) {
            $pr = $this->em->find(PressRelease::class, $prId);
            if ($pr === null) {
                continue;
            }

            $this->clusterSinglePressRelease($pr, $dryRun);
            $processed++;

            // Flush in batches of 20
            if (!$dryRun && $processed % 20 === 0) {
                $this->em->flush();
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $this->logger->info('ClusteringService: processed {count} PressReleases', [
            'count' => $processed,
        ]);

        return $processed;
    }

    /**
     * Cluster a single PressRelease — find matching cluster or create new one.
     */
    public function clusterSinglePressRelease(PressRelease $pr, bool $dryRun = false): ?StoryCluster
    {
        $title = $pr->getTitle();
        $content = $pr->getContent();

        // Search for similar articles in existing clusters
        $matchingCluster = $this->findMatchingCluster($pr);

        if ($matchingCluster !== null) {
            $this->logger->info('ClusteringService: adding PR #{id} to existing cluster #{clusterId}', [
                'id' => $pr->getId(),
                'clusterId' => $matchingCluster->getId(),
                'clusterHeadline' => mb_substr($matchingCluster->getPrimaryHeadline(), 0, 60),
            ]);

            if (!$dryRun) {
                $matchingCluster->addPressRelease($pr);
                $matchingCluster->recalculateCounts();
                $matchingCluster->setLastUpdatedAt(new \DateTimeImmutable());
            }

            return $matchingCluster;
        }

        // No match — create new cluster
        $this->logger->info('ClusteringService: creating new cluster for PR #{id}', [
            'id' => $pr->getId(),
            'title' => mb_substr($title, 0, 60),
        ]);

        if (!$dryRun) {
            $cluster = new StoryCluster();
            $cluster->setPrimaryHeadline(mb_substr($title, 0, 500));
            $cluster->addPressRelease($pr);
            $cluster->recalculateCounts();
            $cluster->setFirstSeenAt($pr->getReceivedAt());
            $cluster->setLastUpdatedAt(new \DateTimeImmutable());

            // Copy region tag from source country
            $hostname = $pr->getSourceHostname();
            if ($hostname !== null) {
                $cluster->setRegionTags([$hostname]);
            }

            $this->em->persist($cluster);

            return $cluster;
        }

        return null;
    }

    /**
     * Find an existing StoryCluster that matches the given PressRelease.
     */
    private function findMatchingCluster(PressRelease $pr): ?StoryCluster
    {
        // Use ES MLT to find similar articles
        $similar = $this->clusterFinder->findSimilar(
            $pr->getTitle(),
            $pr->getContent(),
            self::SIMILARITY_THRESHOLD,
        );

        if ($similar === []) {
            return null;
        }

        // Find clusters containing any of the similar articles
        $since = new \DateTimeImmutable(sprintf('-%d hours', self::TEMPORAL_WINDOW_HOURS));
        $activeClusters = $this->clusterRepository->findActiveClustersInWindow($since);

        foreach ($similar as $match) {
            foreach ($activeClusters as $cluster) {
                // Check if any PressRelease in this cluster matches the similar article
                foreach ($cluster->getPressReleases() as $clusteredPr) {
                    if ($clusteredPr->getId() === $match['articleId']) {
                        // Temporal validation — must be within 48h
                        if ($this->isWithinTemporalWindow($pr, $cluster)) {
                            return $cluster;
                        }
                    }
                }
            }
        }

        // Also try matching by title against cluster headlines directly
        foreach ($activeClusters as $cluster) {
            if ($this->isTitleSimilar($pr->getTitle(), $cluster->getPrimaryHeadline())) {
                if ($this->isWithinTemporalWindow($pr, $cluster)) {
                    return $cluster;
                }
            }
        }

        return null;
    }

    private function isWithinTemporalWindow(PressRelease $pr, StoryCluster $cluster): bool
    {
        $prTime = $pr->getReceivedAt()->getTimestamp();
        $clusterTime = $cluster->getFirstSeenAt()->getTimestamp();
        $diffHours = abs($prTime - $clusterTime) / 3600;

        return $diffHours <= self::TEMPORAL_WINDOW_HOURS;
    }

    /**
     * Simple title similarity check using normalized word overlap.
     */
    private function isTitleSimilar(string $title1, string $title2): bool
    {
        $words1 = $this->normalizeWords($title1);
        $words2 = $this->normalizeWords($title2);

        if ($words1 === [] || $words2 === []) {
            return false;
        }

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        $jaccard = \count($intersection) / \count($union);

        return $jaccard >= 0.5;
    }

    /**
     * @return list<string>
     */
    private function normalizeWords(string $text): array
    {
        $text = mb_strtolower(trim($text));
        $words = preg_split('/\s+/', $text, -1, \PREG_SPLIT_NO_EMPTY);

        // Filter out common stop words and short words
        return array_values(array_filter(
            $words ?: [],
            fn (string $w) => mb_strlen($w) > 2,
        ));
    }
}
