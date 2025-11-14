<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LiveText;
use App\Entity\LiveTextAbTest;
use App\Entity\User;
use App\Repository\LiveTextAbTestRepository;
use App\Repository\LiveTextPostEngagementRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A/B Test Service.
 *
 * Manages A/B testing experiments for LiveText
 */
class LiveTextAbTestService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LiveTextAbTestRepository $abTestRepository,
        private LiveTextPostEngagementRepository $engagementRepository,
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Create a new A/B test.
     */
    public function createTest(
        string $name,
        string $variantType,
        array $controlVariant,
        array $testVariants,
        string $targetMetric,
        User $createdBy,
        ?string $description = null,
        ?string $hypothesis = null,
        int $trafficAllocation = 100,
        ?int $minSampleSize = null,
        ?string $significanceLevel = '0.05'
    ): LiveTextAbTest {
        $test = new LiveTextAbTest();
        $test->setName($name);
        $test->setDescription($description);
        $test->setHypothesis($hypothesis);
        $test->setVariantType($variantType);
        $test->setControlVariant($controlVariant);
        $test->setTestVariants($testVariants);
        $test->setTargetMetric($targetMetric);
        $test->setTrafficAllocation($trafficAllocation);
        $test->setMinSampleSize($minSampleSize);
        $test->setSignificanceLevel($significanceLevel);
        $test->setCreatedBy($createdBy);
        $test->setStatus('draft');

        $this->entityManager->persist($test);
        $this->entityManager->flush();

        $this->logger->info('A/B test created', [
            'test_id' => $test->getId(),
            'name' => $name,
            'variant_type' => $variantType,
        ]);

        return $test;
    }

    /**
     * Start an A/B test.
     */
    public function startTest(LiveTextAbTest $test): void
    {
        if ($test->getStatus() !== 'draft' && $test->getStatus() !== 'paused') {
            throw new RuntimeException('Can only start tests in draft or paused status');
        }

        $test->setStatus('running');
        $test->setStartDate(new DateTime());

        $this->entityManager->flush();

        $this->logger->info('A/B test started', [
            'test_id' => $test->getId(),
            'name' => $test->getName(),
        ]);
    }

    /**
     * Pause an A/B test.
     */
    public function pauseTest(LiveTextAbTest $test): void
    {
        if ($test->getStatus() !== 'running') {
            throw new RuntimeException('Can only pause running tests');
        }

        $test->setStatus('paused');
        $this->entityManager->flush();

        $this->logger->info('A/B test paused', [
            'test_id' => $test->getId(),
            'name' => $test->getName(),
        ]);
    }

    /**
     * Complete an A/B test.
     */
    public function completeTest(LiveTextAbTest $test, ?string $winnerVariant = null): void
    {
        $test->setStatus('completed');
        $test->setEndDate(new DateTime());

        if ($winnerVariant) {
            $test->setWinnerVariant($winnerVariant);
        }

        $this->entityManager->flush();

        $this->logger->info('A/B test completed', [
            'test_id' => $test->getId(),
            'name' => $test->getName(),
            'winner' => $winnerVariant,
        ]);
    }

    /**
     * Assign variant to user/session
     * Returns variant key (e.g., 'control', 'variant_a', 'variant_b').
     */
    public function assignVariant(LiveTextAbTest $test, ?string $sessionId = null): string
    {
        if ($test->getStatus() !== 'running') {
            return 'control';
        }

        // Check if session already has assigned variant (stored in session)
        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            $sessionKey = 'ab_test_' . $test->getId();

            if ($session->has($sessionKey)) {
                return $session->get($sessionKey);
            }
        }

        // Assign new variant based on traffic allocation
        $sessionId ??= $this->getOrCreateSessionId();
        $variant = $this->selectVariant($test, $sessionId);

        // Store in session
        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            $session->set('ab_test_' . $test->getId(), $variant);
        }

        $this->logger->debug('Variant assigned', [
            'test_id' => $test->getId(),
            'session_id' => substr($sessionId, 0, 8),
            'variant' => $variant,
        ]);

        return $variant;
    }

    /**
     * Get variant configuration for a test.
     */
    public function getVariantConfig(LiveTextAbTest $test, string $variantKey): array
    {
        if ($variantKey === 'control') {
            return $test->getControlVariant();
        }

        $testVariants = $test->getTestVariants();

        return $testVariants[$variantKey] ?? $test->getControlVariant();
    }

    /**
     * Calculate test results and statistical significance.
     */
    public function calculateResults(LiveTextAbTest $test): array
    {
        $liveTexts = $test->getLiveTexts();
        if ($liveTexts->isEmpty()) {
            return [
                'status' => 'no_data',
                'message' => 'No LiveTexts associated with this test',
            ];
        }

        $targetMetric = $test->getTargetMetric();

        // Collect metrics for each variant
        $variantMetrics = [
            'control' => $this->collectVariantMetrics($test, 'control', $targetMetric),
        ];

        foreach ($test->getTestVariants() as $variantKey => $variantConfig) {
            $variantMetrics[$variantKey] = $this->collectVariantMetrics($test, $variantKey, $targetMetric);
        }

        // Calculate statistical significance
        $controlMetrics = $variantMetrics['control'];
        $results = [
            'variants' => $variantMetrics,
            'comparisons' => [],
            'winner' => null,
            'confidence_level' => null,
        ];

        foreach ($test->getTestVariants() as $variantKey => $variantConfig) {
            $testMetrics = $variantMetrics[$variantKey];

            $comparison = $this->compareVariants($controlMetrics, $testMetrics, $test->getSignificanceLevel());
            $results['comparisons'][$variantKey] = $comparison;

            // Determine winner
            if ($comparison['is_significant'] && $comparison['improvement'] > 0) {
                if (!$results['winner'] || $comparison['improvement'] > $results['comparisons'][$results['winner']]['improvement']) {
                    $results['winner'] = $variantKey;
                    $results['confidence_level'] = $comparison['confidence_level'];
                }
            }
        }

        // If no test variant won, control is the winner
        if (!$results['winner']) {
            $results['winner'] = 'control';
            $results['confidence_level'] = '100.00';
        }

        // Update test results
        $test->setResults($results);
        $test->setWinnerVariant($results['winner']);
        $test->setConfidenceLevel($results['confidence_level']);
        $this->entityManager->flush();

        return $results;
    }

    /**
     * Get running tests for a LiveText.
     */
    public function getRunningTestsForLiveText(LiveText $liveText): array
    {
        $allRunningTests = $this->abTestRepository->findRunningTests();

        $tests = [];
        foreach ($allRunningTests as $test) {
            if ($test->getLiveTexts()->contains($liveText)) {
                $tests[] = $test;
            }
        }

        return $tests;
    }

    /**
     * Add LiveText to test.
     */
    public function addLiveTextToTest(LiveTextAbTest $test, LiveText $liveText): void
    {
        $test->addLiveText($liveText);
        $this->entityManager->flush();

        $this->logger->info('LiveText added to A/B test', [
            'test_id' => $test->getId(),
            'livetext_id' => $liveText->getId(),
        ]);
    }

    /**
     * Remove LiveText from test.
     */
    public function removeLiveTextFromTest(LiveTextAbTest $test, LiveText $liveText): void
    {
        $test->removeLiveText($liveText);
        $this->entityManager->flush();

        $this->logger->info('LiveText removed from A/B test', [
            'test_id' => $test->getId(),
            'livetext_id' => $liveText->getId(),
        ]);
    }

    /**
     * Get test summary.
     */
    public function getTestSummary(LiveTextAbTest $test): array
    {
        return [
            'id' => $test->getId(),
            'name' => $test->getName(),
            'status' => $test->getStatus(),
            'variant_type' => $test->getVariantType(),
            'target_metric' => $test->getTargetMetric(),
            'start_date' => $test->getStartDate()?->format('Y-m-d H:i:s'),
            'end_date' => $test->getEndDate()?->format('Y-m-d H:i:s'),
            'winner_variant' => $test->getWinnerVariant(),
            'confidence_level' => $test->getConfidenceLevel(),
            'livetext_count' => $test->getLiveTexts()->count(),
            'results' => $test->getResults(),
        ];
    }

    /**
     * Collect metrics for a variant.
     */
    private function collectVariantMetrics(LiveTextAbTest $test, string $variantKey, string $targetMetric): array
    {
        // In a real implementation, you would query engagement data filtered by variant
        // For now, we'll return mock structure

        return [
            'variant' => $variantKey,
            'sample_size' => 0, // TODO: Query actual data
            'metric_value' => 0.0,
            'conversion_rate' => 0.0,
            'avg_time_spent' => 0.0,
            'engagement_rate' => 0.0,
        ];
    }

    /**
     * Compare two variants using statistical tests
     * Uses Z-test for proportions (conversion rates).
     */
    private function compareVariants(array $control, array $test, ?string $significanceLevel = '0.05'): array
    {
        $alpha = (float) ($significanceLevel ?? '0.05');

        $n1 = $control['sample_size'];
        $n2 = $test['sample_size'];

        if ($n1 === 0 || $n2 === 0) {
            return [
                'is_significant' => false,
                'p_value' => 1.0,
                'confidence_level' => '0.00',
                'improvement' => 0.0,
                'message' => 'Insufficient data',
            ];
        }

        $p1 = $control['conversion_rate'] / 100;
        $p2 = $test['conversion_rate'] / 100;

        // Pooled proportion
        $p = (($p1 * $n1) + ($p2 * $n2)) / ($n1 + $n2);

        // Standard error
        $se = sqrt($p * (1 - $p) * ((1 / $n1) + (1 / $n2)));

        // Z-score
        $z = $se > 0 ? ($p2 - $p1) / $se : 0;

        // Two-tailed p-value (approximation)
        $pValue = $this->calculatePValue(abs($z));

        // Improvement percentage
        $improvement = $p1 > 0 ? (($p2 - $p1) / $p1) * 100 : 0;

        return [
            'is_significant' => $pValue < $alpha,
            'p_value' => round($pValue, 4),
            'confidence_level' => round((1 - $pValue) * 100, 2),
            'z_score' => round($z, 4),
            'improvement' => round($improvement, 2),
            'message' => $pValue < $alpha
                ? \sprintf('Statistically significant improvement of %.2f%%', $improvement)
                : 'No significant difference detected',
        ];
    }

    /**
     * Calculate p-value from z-score (approximation)
     * Uses standard normal distribution.
     */
    private function calculatePValue(float $z): float
    {
        // Approximation using error function
        // For more accuracy, use a statistics library
        $t = 1 / (1 + 0.2316419 * $z);
        $d = 0.3989423 * exp(-$z * $z / 2);
        $probability = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));

        return 2 * $probability; // Two-tailed
    }

    /**
     * Select variant based on traffic allocation and consistent hashing.
     */
    private function selectVariant(LiveTextAbTest $test, string $sessionId): string
    {
        // Use consistent hashing to ensure same session always gets same variant
        $hash = crc32($sessionId . $test->getId());
        $percentage = $hash % 100;

        // Check traffic allocation
        if ($percentage >= $test->getTrafficAllocation()) {
            return 'control';
        }

        // Distribute evenly among control and test variants
        $variants = ['control'];
        foreach ($test->getTestVariants() as $variantKey => $variantConfig) {
            $variants[] = $variantKey;
        }

        $variantIndex = $hash % \count($variants);

        return $variants[$variantIndex];
    }

    /**
     * Get or create session ID.
     */
    private function getOrCreateSessionId(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return bin2hex(random_bytes(16));
        }

        $session = $request->getSession();
        if (!$session->has('ab_test_session_id')) {
            $session->set('ab_test_session_id', bin2hex(random_bytes(16)));
        }

        return $session->get('ab_test_session_id');
    }
}
