<?php

declare(strict_types=1);

namespace App\Service\Clustering;

use App\Entity\PressRelease;
use App\Entity\StoryCluster;
use App\Repository\AppSettingRepository;
use App\Repository\PressReleaseRepository;
use App\Repository\StoryClusterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Groups PressReleases into StoryCluster entities based on content similarity.
 *
 * Algorithm:
 * 1. Find PressReleases not yet assigned to any cluster
 * 2. For each, search ES MLT against other indexed PressReleases
 * 3. Map matched PR IDs to their existing StoryCluster
 * 4. Temporal validation: articles must be within configured window of each other
 * 5. If match → add to existing cluster
 * 6. If no match → create new StoryCluster
 */
class ClusteringService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StoryClusterRepository $clusterRepository,
        private readonly PressReleaseRepository $pressReleaseRepository,
        private readonly ElasticsearchClusterFinder $clusterFinder,
        private readonly PressReleaseIndexer $indexer,
        private readonly SemanticClusterVerifier $verifier,
        private readonly AppSettingRepository $appSettings,
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

            // Ensure the PR is indexed in ES before trying to cluster
            $this->indexer->index($pr);

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
            'title' => mb_substr($pr->getTitle(), 0, 60),
        ]);

        if (!$dryRun) {
            $cluster = new StoryCluster();
            $cluster->setPrimaryHeadline(mb_substr($pr->getTitle(), 0, 500));
            $cluster->addPressRelease($pr);
            $cluster->recalculateCounts();
            $cluster->setFirstSeenAt($pr->getReceivedAt());
            $cluster->setLastUpdatedAt(new \DateTimeImmutable());

            $this->em->persist($cluster);

            return $cluster;
        }

        return null;
    }

    /**
     * Find an existing StoryCluster that matches the given PressRelease.
     *
     * Strategy:
     * 1. ES MLT finds similar PressReleases by content
     * 2. Look up which clusters those similar PRs belong to
     * 3. Return the best matching cluster (temporal validation)
     */
    private function findMatchingCluster(PressRelease $pr): ?StoryCluster
    {
        // Step 0: Content hash dedup — skip MLT if an identical PR is already clustered
        $dedupCluster = $this->findDuplicateCluster($pr);
        if ($dedupCluster !== null) {
            return $dedupCluster;
        }

        // Step 1: Find similar PressReleases via ES MLT
        $similar = $this->clusterFinder->findSimilar(
            $pr->getTitle(),
            $pr->getContent(),
            excludeId: $pr->getId(),
        );

        if ($similar === []) {
            return null;
        }

        // Step 2: Collect matched PR IDs and find their clusters
        $matchedPrIds = array_map(fn (array $m) => $m['pressReleaseId'], $similar);

        // Query clusters that contain any of the matched PRs
        $clusters = $this->clusterRepository->findClustersContainingPressReleases($matchedPrIds);

        if ($clusters === []) {
            return null;
        }

        // Step 3: Filter by temporal window
        $temporalWindowHours = $this->getTemporalWindowHours();
        $temporalCandidates = [];
        foreach ($clusters as $cluster) {
            if ($this->isWithinTemporalWindow($pr, $cluster, $temporalWindowHours)) {
                $temporalCandidates[] = $cluster;
            }
        }

        if ($temporalCandidates === []) {
            return null;
        }

        // Step 4: Semantic verification gate (if enabled)
        if ($this->verifier->isEnabled()) {
            $minConfidence = $this->appSettings->getFloat('cluster_semantic_min_confidence', 0.70);

            foreach ($temporalCandidates as $cluster) {
                $result = $this->verifier->verify($pr->getTitle(), $cluster->getPrimaryHeadline());

                if ($result->sameStory && $result->confidence >= $minConfidence) {
                    return $cluster;
                }

                $this->logger->info('ClusteringService: semantic verification rejected PR #{prId} from cluster #{clusterId}', [
                    'prId' => $pr->getId(),
                    'clusterId' => $cluster->getId(),
                    'clusterHeadline' => mb_substr($cluster->getPrimaryHeadline(), 0, 80),
                    'reason' => $result->reason,
                    'confidence' => $result->confidence,
                    'sameStory' => $result->sameStory,
                ]);
            }

            return null;
        }

        return $temporalCandidates[0];
    }

    private function isWithinTemporalWindow(PressRelease $pr, StoryCluster $cluster, int $windowHours): bool
    {
        $prTime = $pr->getReceivedAt()->getTimestamp();
        $clusterTime = $cluster->getFirstSeenAt()->getTimestamp();
        $diffHours = abs($prTime - $clusterTime) / 3600;

        return $diffHours <= $windowHours;
    }

    private function getTemporalWindowHours(): int
    {
        return $this->appSettings->getInt('cluster_temporal_window_hours', 48);
    }

    private function findDuplicateCluster(PressRelease $pr): ?StoryCluster
    {
        $hash = $pr->getContentHash();
        if ($hash === null || $pr->getId() === null) {
            return null;
        }

        $duplicate = $this->pressReleaseRepository->findClusteredDuplicateByHash($hash, $pr->getId());
        if ($duplicate === null) {
            return null;
        }

        // Find cluster via repository (reliable even without Doctrine hydration on inverse side)
        $clusters = $this->clusterRepository->findClustersContainingPressReleases([$duplicate->getId()]);
        if ($clusters === []) {
            return null;
        }

        $cluster = $clusters[0];

        $this->logger->info('ClusteringService: content hash dedup — PR #{id} matches PR #{dupId} in cluster #{clusterId}', [
            'id' => $pr->getId(),
            'dupId' => $duplicate->getId(),
            'clusterId' => $cluster->getId(),
        ]);

        return $cluster;
    }
}
