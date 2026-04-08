<?php

declare(strict_types=1);

namespace App\Service\Clustering;

use App\Entity\StoryCluster;
use App\Repository\SourceRepository;
use Psr\Log\LoggerInterface;

/**
 * Calculates importance score for a StoryCluster using 6 weighted factors.
 *
 * Formula (Sprint 32 calibrated):
 *   0.25 * sourceWeightedCount  — credibility of unique sources / 3.0
 *   0.15 * recency              — exp(-0.02 × hours) with floor 0.05
 *   0.15 * geoDiversity         — distinct countries / 3.0
 *   0.15 * topicCriticality     — avg topic weight (default 0.5)
 *   0.15 * velocity             — PRs in last 12h / 5.0
 *   0.15 * coverageDepth        — article_count / 5.0 (new factor)
 *   × editorialBoost            — multiplier (default 1.0)
 *
 * All factors normalized to 0.0–1.0 before weighting.
 */
class ImportanceScoreCalculator
{
    private const WEIGHT_SOURCE = 0.25;
    private const WEIGHT_RECENCY = 0.15;
    private const WEIGHT_GEO_DIVERSITY = 0.15;
    private const WEIGHT_TOPIC_CRITICALITY = 0.10;
    private const WEIGHT_VELOCITY = 0.10;
    private const WEIGHT_COVERAGE_DEPTH = 0.25;

    // Recency decay: exp(-λ × hours). λ=0.02 → 24h=0.62, 48h=0.38, 7d=0.04
    private const RECENCY_LAMBDA = 0.02;
    private const RECENCY_FLOOR = 0.05;

    // Source: sum(credibility) / DIVISOR, capped at 1.0
    private const SOURCE_DIVISOR = 3.0;

    // Geo: distinct countries / DIVISOR, capped at 1.0
    private const GEO_DIVISOR = 3.0;

    // Velocity: PRs in window / DIVISOR
    private const VELOCITY_WINDOW_HOURS = 12;
    private const VELOCITY_DIVISOR = 5.0;

    // Coverage depth: article_count / DIVISOR
    private const COVERAGE_DIVISOR = 5.0;

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
        $coverageDepth = $this->calculateCoverageDepth($cluster);

        $baseScore = self::WEIGHT_SOURCE * $sourceWeighted
            + self::WEIGHT_RECENCY * $recency
            + self::WEIGHT_GEO_DIVERSITY * $geoDiversity
            + self::WEIGHT_TOPIC_CRITICALITY * $topicCriticality
            + self::WEIGHT_VELOCITY * $velocity
            + self::WEIGHT_COVERAGE_DEPTH * $coverageDepth;

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
            'coverageDepth' => round($coverageDepth, 3),
            'editorialBoost' => $editorialBoost,
            'finalScore' => round($finalScore, 4),
        ]);

        return round($finalScore, 4);
    }

    /**
     * Sum of unique sources' credibilityWeight / 3.0 (capped at 1.0).
     * PRs without Source FK get default weight 0.50.
     */
    private function calculateSourceWeightedCount(StoryCluster $cluster): float
    {
        $uniqueSources = [];
        foreach ($cluster->getPressReleases() as $pr) {
            $source = $pr->getSource();
            if ($source !== null) {
                $key = 'source_' . $source->getId();
                if (!isset($uniqueSources[$key])) {
                    $uniqueSources[$key] = $source->getCredibilityWeight();
                }
                continue;
            }

            // Fallback: hostname-based lookup, then default 0.50
            $hostname = $pr->getSourceHostname();
            $key = $hostname ?? ('pr_' . $pr->getId());
            if (!isset($uniqueSources[$key])) {
                if ($hostname !== null) {
                    $uniqueSources[$key] = $this->sourceRepository->getCredibilityWeight($hostname);
                } else {
                    $uniqueSources[$key] = 0.50;
                }
            }
        }

        if ($uniqueSources === []) {
            return 0.0;
        }

        return min(1.0, array_sum($uniqueSources) / self::SOURCE_DIVISOR);
    }

    /**
     * Exponential decay with floor: max(FLOOR, exp(-λ × hours)).
     * λ=0.02: 1h=0.98, 24h=0.62, 48h=0.38, 7d=0.04, floor=0.05.
     */
    private function calculateRecency(StoryCluster $cluster): float
    {
        $hoursSince = (time() - $cluster->getFirstSeenAt()->getTimestamp()) / 3600;
        $hoursSince = max(0.0, $hoursSince);

        return max(self::RECENCY_FLOOR, exp(-self::RECENCY_LAMBDA * $hoursSince));
    }

    /**
     * Count of distinct countries / 3 (capped at 1.0).
     */
    private function calculateGeoDiversity(StoryCluster $cluster): float
    {
        $countries = [];
        foreach ($cluster->getPressReleases() as $pr) {
            $source = $pr->getSource();
            if ($source !== null && $source->getCountry() !== null) {
                $countries[$source->getCountry()] = true;
                continue;
            }

            $hostname = $pr->getSourceHostname();
            if ($hostname !== null) {
                $source = $this->sourceRepository->findByDomain($hostname);
                if ($source !== null && $source->getCountry() !== null) {
                    $countries[$source->getCountry()] = true;
                }
            }
        }

        return min(1.0, \count($countries) / self::GEO_DIVISOR);
    }

    /**
     * Average of topic weights (default 0.5 if no topics assigned).
     */
    private function calculateTopicCriticality(StoryCluster $cluster): float
    {
        $topics = $cluster->getTopics();

        if ($topics->isEmpty()) {
            return 0.5;
        }

        $totalWeight = 0.0;
        foreach ($topics as $topic) {
            $totalWeight += $topic->getWeight();
        }

        return $totalWeight / $topics->count();
    }

    /**
     * Count of PressReleases created in last 12 hours / 5 (capped at 1.0).
     */
    private function calculateVelocity(StoryCluster $cluster): float
    {
        $windowStart = time() - (self::VELOCITY_WINDOW_HOURS * 3600);
        $recentCount = 0;

        foreach ($cluster->getPressReleases() as $pr) {
            if ($pr->getCreatedAt()->getTimestamp() >= $windowStart) {
                $recentCount++;
            }
        }

        return min(1.0, $recentCount / self::VELOCITY_DIVISOR);
    }

    /**
     * How many articles cover this story / 5 (capped at 1.0).
     * Rewards multi-article clusters that represent significant events.
     */
    private function calculateCoverageDepth(StoryCluster $cluster): float
    {
        $articleCount = $cluster->getArticleCount();
        if ($articleCount <= 0) {
            $articleCount = $cluster->getPressReleases()->count();
        }

        return min(1.0, $articleCount / self::COVERAGE_DIVISOR);
    }
}
