<?php

declare(strict_types=1);

namespace App\Service\Clustering;

use App\Entity\StoryCluster;
use App\Repository\SourceRepository;
use Psr\Log\LoggerInterface;

/**
 * Calculates importance score for a StoryCluster using 6 weighted factors.
 *
 * Formula:
 *   0.25 * sourceWeightedCount
 * + 0.20 * recency
 * + 0.15 * geoDiversity
 * + 0.15 * topicCriticality
 * + 0.15 * velocity
 * + 0.10 * editorialBoost (applied as multiplier)
 *
 * All factors are normalized to 0.0–1.0 range before weighting.
 */
class ImportanceScoreCalculator
{
    private const WEIGHT_SOURCE = 0.25;
    private const WEIGHT_RECENCY = 0.20;
    private const WEIGHT_GEO_DIVERSITY = 0.15;
    private const WEIGHT_TOPIC_CRITICALITY = 0.15;
    private const WEIGHT_VELOCITY = 0.15;
    private const WEIGHT_EDITORIAL = 0.10;

    public function __construct(
        private readonly SourceRepository $sourceRepository,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Calculate importance score for a cluster (0.0–1.0+ range).
     */
    public function calculate(StoryCluster $cluster): float
    {
        $sourceWeighted = $this->calculateSourceWeightedCount($cluster);
        $recency = $this->calculateRecency($cluster);
        $geoDiversity = $this->calculateGeoDiversity($cluster);
        $topicCriticality = $this->calculateTopicCriticality($cluster);
        $velocity = $this->calculateVelocity($cluster);

        $baseScore = self::WEIGHT_SOURCE * $sourceWeighted
            + self::WEIGHT_RECENCY * $recency
            + self::WEIGHT_GEO_DIVERSITY * $geoDiversity
            + self::WEIGHT_TOPIC_CRITICALITY * $topicCriticality
            + self::WEIGHT_VELOCITY * $velocity;

        // Editorial boost is a multiplier (default 1.0)
        $editorialBoost = $cluster->getEditorialBoost();
        $finalScore = $baseScore * $editorialBoost;

        $this->logger->debug('ImportanceScoreCalculator: cluster #{id}', [
            'id' => $cluster->getId(),
            'sourceWeighted' => round($sourceWeighted, 3),
            'recency' => round($recency, 3),
            'geoDiversity' => round($geoDiversity, 3),
            'topicCriticality' => round($topicCriticality, 3),
            'velocity' => round($velocity, 3),
            'editorialBoost' => $editorialBoost,
            'finalScore' => round($finalScore, 4),
        ]);

        return round($finalScore, 4);
    }

    /**
     * Sum of unique sources' credibilityWeight / 5.0 (capped at 1.0).
     */
    private function calculateSourceWeightedCount(StoryCluster $cluster): float
    {
        $uniqueSources = [];
        foreach ($cluster->getPressReleases() as $pr) {
            $hostname = $pr->getSourceHostname();
            if ($hostname !== null && !isset($uniqueSources[$hostname])) {
                $weight = $this->sourceRepository->getCredibilityWeight($hostname);
                $uniqueSources[$hostname] = $weight;
            }
        }

        if ($uniqueSources === []) {
            return 0.0;
        }

        $total = array_sum($uniqueSources);

        return min(1.0, $total / 5.0);
    }

    /**
     * Exponential decay: exp(-0.1 * hours_since_firstSeen).
     * 1h=0.90, 12h=0.30, 24h=0.09.
     */
    private function calculateRecency(StoryCluster $cluster): float
    {
        $hoursSince = (time() - $cluster->getFirstSeenAt()->getTimestamp()) / 3600;
        $hoursSince = max(0.0, $hoursSince);

        return exp(-0.1 * $hoursSince);
    }

    /**
     * Count of distinct countries / 5 (capped at 1.0).
     */
    private function calculateGeoDiversity(StoryCluster $cluster): float
    {
        $countries = [];
        foreach ($cluster->getPressReleases() as $pr) {
            $hostname = $pr->getSourceHostname();
            if ($hostname !== null) {
                $source = $this->sourceRepository->findByDomain($hostname);
                if ($source !== null && $source->getCountry() !== null) {
                    $countries[$source->getCountry()] = true;
                }
            }
        }

        $count = \count($countries);

        return min(1.0, $count / 5.0);
    }

    /**
     * Average of topic weights (default 0.5 if no topics assigned).
     */
    private function calculateTopicCriticality(StoryCluster $cluster): float
    {
        $topics = $cluster->getTopics();

        if ($topics->isEmpty()) {
            return 0.5; // default weight
        }

        $totalWeight = 0.0;
        foreach ($topics as $topic) {
            $totalWeight += $topic->getWeight();
        }

        return $totalWeight / $topics->count();
    }

    /**
     * Count of PressReleases created in last 4 hours / 10 (capped at 1.0).
     */
    private function calculateVelocity(StoryCluster $cluster): float
    {
        $fourHoursAgo = time() - (4 * 3600);
        $recentCount = 0;

        foreach ($cluster->getPressReleases() as $pr) {
            if ($pr->getCreatedAt()->getTimestamp() >= $fourHoursAgo) {
                $recentCount++;
            }
        }

        return min(1.0, $recentCount / 10.0);
    }
}
