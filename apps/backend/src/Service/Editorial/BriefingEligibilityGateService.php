<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Dto\Editorial\BriefingEligibilityDecision;
use App\Entity\Topic;
use App\Enum\BriefingCadence;
use App\Repository\AppSettingRepository;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Evaluates whether a topic is eligible for briefing generation at a given cadence.
 *
 * Per-cadence thresholds (ADR-016 D6, amended 2026-04-21):
 * - Hourly: min 3 PRs, avg confidence >= 0.85 (breaking news only, ~90% filter)
 * - Daily:  min 5 PRs, avg confidence >= 0.70 (~60% filter)
 * - Weekly: min 10 PRs, avg confidence >= 0.55 (~30% filter)
 *
 * "avgRelevance" in the public decision DTO corresponds to
 * AVG(PressReleaseTopic.confidence) — a probability in [0.0, 1.0] emitted by
 * the LLM classifier. The original ADR thresholds (3.0/2.5/2.0) were on an
 * unimplemented 0-5 scale; the 2026-04-21 amendment rescaled them to the
 * probability scale actually stored in `press_release_topics.confidence`.
 *
 * Window filter uses `pr.receivedAt` (ingestion time) to match
 * TopicBriefingWriterService::findPressReleases and newsroom semantics
 * ("briefing of last hour" = news ingested in last hour).
 */
class BriefingEligibilityGateService
{
    public function __construct(
        private readonly AppSettingRepository $settings,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function evaluate(Topic $topic, BriefingCadence $cadence, DateRange $range): BriefingEligibilityDecision
    {
        $reasons = [];

        // 1. Global briefing enabled?
        if (!$this->settings->getBool('briefing.enabled', true)) {
            return new BriefingEligibilityDecision(false, $cadence, 0, 0.0, ['briefing_disabled']);
        }

        // 2. Cadence-specific enabled?
        $cadenceKey = 'briefing.' . $cadence->value . '.enabled';
        if (!$this->settings->getBool($cadenceKey, true)) {
            return new BriefingEligibilityDecision(false, $cadence, 0, 0.0, ['cadence_disabled']);
        }

        // 3. Topic must be active
        if (!$topic->isActive()) {
            return new BriefingEligibilityDecision(false, $cadence, 0, 0.0, ['topic_inactive']);
        }

        // 4. Query PR metrics for this topic within the date range
        $metrics = $this->queryPressReleaseMetrics($topic, $range);
        $prCount = $metrics['count'];
        $avgRelevance = $metrics['avgRelevance'];

        // 5. Min PR count check
        $minPrKey = 'briefing.' . $cadence->value . '.min_pr_count';
        $minPrCount = $this->settings->getInt($minPrKey, $this->defaultMinPrCount($cadence));

        if ($prCount < $minPrCount) {
            $reasons[] = sprintf('low_pr_count:%d_min:%d', $prCount, $minPrCount);
        }

        // 6. Min avg relevance check
        $minRelKey = 'briefing.' . $cadence->value . '.min_avg_relevance';
        $minAvgRelevance = $this->settings->getFloat($minRelKey, $this->defaultMinAvgRelevance($cadence));

        if ($prCount > 0 && $avgRelevance < $minAvgRelevance) {
            $reasons[] = sprintf('low_relevance:%.2f_min:%.2f', $avgRelevance, $minAvgRelevance);
        }

        $eligible = $reasons === [];

        $this->logger->info('BriefingEligibilityGate: evaluated', [
            'topicId' => $topic->getId(),
            'cadence' => $cadence->value,
            'eligible' => $eligible,
            'prCount' => $prCount,
            'avgRelevance' => $avgRelevance,
            'reasons' => $reasons,
        ]);

        return new BriefingEligibilityDecision($eligible, $cadence, $prCount, $avgRelevance, $reasons);
    }

    /**
     * @return array{count: int, avgRelevance: float}
     */
    private function queryPressReleaseMetrics(Topic $topic, DateRange $range): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(prt.id) AS cnt, AVG(prt.confidence) AS avgConf')
            ->from(\App\Entity\PressReleaseTopic::class, 'prt')
            ->join('prt.pressRelease', 'pr')
            ->where('prt.topic = :topic')
            ->andWhere('pr.receivedAt >= :from')
            ->andWhere('pr.receivedAt <= :to')
            ->setParameter('topic', $topic)
            ->setParameter('from', $range->from)
            ->setParameter('to', $range->to);

        $result = $qb->getQuery()->getSingleResult();

        return [
            'count' => (int) ($result['cnt'] ?? 0),
            'avgRelevance' => (float) ($result['avgConf'] ?? 0.0),
        ];
    }

    private function defaultMinPrCount(BriefingCadence $cadence): int
    {
        return match ($cadence) {
            BriefingCadence::HOURLY => 3,
            BriefingCadence::DAILY => 5,
            BriefingCadence::WEEKLY => 10,
        };
    }

    private function defaultMinAvgRelevance(BriefingCadence $cadence): float
    {
        return match ($cadence) {
            BriefingCadence::HOURLY => 0.85,
            BriefingCadence::DAILY => 0.70,
            BriefingCadence::WEEKLY => 0.55,
        };
    }
}
