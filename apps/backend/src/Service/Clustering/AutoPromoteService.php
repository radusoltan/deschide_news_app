<?php

declare(strict_types=1);

namespace App\Service\Clustering;

use App\Entity\PressRelease;
use App\Entity\StoryCluster;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Enum\StoryClusterStatus;
use App\Repository\AppSettingRepository;
use App\Repository\StoryClusterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class AutoPromoteService
{
    public const SETTING_KEY = 'auto_promote_threshold';
    public const DEFAULT_THRESHOLD = 0.7;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StoryClusterRepository $clusterRepository,
        private readonly AppSettingRepository $settingRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function getThreshold(): float
    {
        $value = $this->settingRepository->get(self::SETTING_KEY);

        return $value !== null ? (float) $value : self::DEFAULT_THRESHOLD;
    }

    public function setThreshold(float $threshold): void
    {
        $clamped = max(0.0, min(1.0, $threshold));
        $this->settingRepository->set(self::SETTING_KEY, (string) $clamped);

        $this->logger->info('Auto-promote threshold updated to {threshold}', [
            'threshold' => $clamped,
        ]);
    }

    /**
     * Promote all eligible clusters above the given threshold.
     *
     * @return array{promoted: int, clusters: list<array{id: int, score: float, headline: string}>}
     */
    public function promoteHighScoreClusters(?float $threshold = null, bool $dryRun = false): array
    {
        $threshold ??= $this->getThreshold();

        $eligible = $this->clusterRepository->createQueryBuilder('c')
            ->where('c.importanceScore >= :threshold')
            ->andWhere('c.status = :status')
            ->andWhere('c.promotedToPressRelease = false')
            ->setParameter('threshold', $threshold)
            ->setParameter('status', StoryClusterStatus::AUTO)
            ->orderBy('c.importanceScore', 'DESC')
            ->getQuery()
            ->getResult();

        $promoted = [];

        foreach ($eligible as $cluster) {
            if ($dryRun) {
                $promoted[] = [
                    'id' => $cluster->getId(),
                    'score' => $cluster->getImportanceScore(),
                    'headline' => $cluster->getPrimaryHeadline(),
                ];
                continue;
            }

            $this->promoteCluster($cluster);
            $promoted[] = [
                'id' => $cluster->getId(),
                'score' => $cluster->getImportanceScore(),
                'headline' => $cluster->getPrimaryHeadline(),
            ];
        }

        if (!$dryRun && \count($promoted) > 0) {
            $this->em->flush();
        }

        return ['promoted' => \count($promoted), 'clusters' => $promoted];
    }

    /**
     * Promote a single cluster: set flags + create synthesized PressRelease.
     */
    public function promoteCluster(StoryCluster $cluster): ?PressRelease
    {
        if ($cluster->isPromotedToPressRelease()) {
            $this->logger->warning('Cluster #{id} already promoted, skipping', [
                'id' => $cluster->getId(),
            ]);
            return null;
        }

        // Only auto-promote clusters with status AUTO
        if ($cluster->getStatus() !== StoryClusterStatus::AUTO
            && $cluster->getStatus() !== StoryClusterStatus::REVIEWED
        ) {
            $this->logger->info('Cluster #{id} has editorial status {status}, skipping auto-promote', [
                'id' => $cluster->getId(),
                'status' => $cluster->getStatus()->value,
            ]);
            return null;
        }

        // Build synthesized PressRelease from cluster
        $pr = new PressRelease();
        $pr->setTitle(mb_substr($cluster->getPrimaryHeadline(), 0, 255));
        $pr->setContent($this->buildContent($cluster));
        $pr->setLead($cluster->getSummaryShort());
        $pr->setCategorySlug('externe');
        $pr->setStatus(PressReleaseStatus::PENDING);
        $pr->setSourceType(SourceType::AGGREGATOR);
        $pr->setSourceName('StoryCluster #' . $cluster->getId());
        $pr->setContentHash(hash('sha256', 'cluster_promote_' . $cluster->getId()));

        // Assign source from highest-credibility PR in cluster
        $bestSource = $this->findHighestCredibilitySource($cluster);
        if ($bestSource !== null) {
            $pr->setSource($bestSource->getSource());
            $pr->setSourceUrl($bestSource->getSourceUrl());
        }

        $this->em->persist($pr);

        // Update cluster flags
        $cluster->setStatus(StoryClusterStatus::PROMOTED);
        $cluster->setPromotedToPressRelease(true);

        $this->logger->info('Auto-promoted cluster #{id} (score={score}) → PressRelease created', [
            'id' => $cluster->getId(),
            'score' => $cluster->getImportanceScore(),
            'headline' => mb_substr($cluster->getPrimaryHeadline(), 0, 80),
        ]);

        return $pr;
    }

    /**
     * Check if a cluster is eligible for auto-promotion (without performing it).
     */
    public function isEligible(StoryCluster $cluster, ?float $threshold = null): bool
    {
        $threshold ??= $this->getThreshold();

        return $cluster->getImportanceScore() >= $threshold
            && $cluster->getStatus() === StoryClusterStatus::AUTO
            && !$cluster->isPromotedToPressRelease();
    }

    private function buildContent(StoryCluster $cluster): string
    {
        // Prefer AI summary
        if ($cluster->getSummaryMedium() !== null) {
            $parts = [$cluster->getSummaryMedium()];

            if ($cluster->getWhyItMatters() !== null) {
                $parts[] = "\n\n**De ce contează:** " . $cluster->getWhyItMatters();
            }

            $keyFacts = $cluster->getKeyFacts();
            if ($keyFacts !== null && \count($keyFacts) > 0) {
                $parts[] = "\n\n**Fapte cheie:**\n- " . implode("\n- ", $keyFacts);
            }

            return implode('', $parts);
        }

        // Fallback: concatenate top PR titles
        $titles = [];
        foreach ($cluster->getPressReleases() as $pr) {
            $titles[] = '- ' . $pr->getTitle();
            if (\count($titles) >= 10) {
                break;
            }
        }

        return $cluster->getPrimaryHeadline() . "\n\n" . implode("\n", $titles);
    }

    private function findHighestCredibilitySource(StoryCluster $cluster): ?PressRelease
    {
        $best = null;
        $bestWeight = -1.0;

        foreach ($cluster->getPressReleases() as $pr) {
            $source = $pr->getSource();
            if ($source === null) {
                continue;
            }
            if ($source->getCredibilityWeight() > $bestWeight) {
                $bestWeight = $source->getCredibilityWeight();
                $best = $pr;
            }
        }

        return $best;
    }
}
