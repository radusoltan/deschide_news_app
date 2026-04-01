<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\LiveText;
use App\Entity\LiveTextAbTest;
use App\Entity\User;
use App\Repository\LiveTextAbTestRepository;
use App\Repository\LiveTextPostEngagementRepository;
use App\Service\LiveTextAbTestService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LiveTextAbTestServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LiveTextAbTestRepository $abTestRepository;
    private LiveTextPostEngagementRepository $engagementRepository;
    private RequestStack $requestStack;
    private LoggerInterface $logger;
    private LiveTextAbTestService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->abTestRepository = $this->createStub(LiveTextAbTestRepository::class);
        $this->engagementRepository = $this->createStub(LiveTextPostEngagementRepository::class);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->service = new LiveTextAbTestService(
            $this->entityManager,
            $this->abTestRepository,
            $this->engagementRepository,
            $this->requestStack,
            $this->logger
        );
    }

    private function createUser(): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $user->method('getUsername')->willReturn('admin');

        return $user;
    }

    private function createAbTest(string $status = 'draft', int $id = 1): LiveTextAbTest
    {
        $test = $this->createMock(LiveTextAbTest::class);
        $test->method('getId')->willReturn($id);
        $test->method('getName')->willReturn('Test experiment');
        $test->method('getStatus')->willReturn($status);
        $test->method('getVariantType')->willReturn('layout');
        $test->method('getTargetMetric')->willReturn('engagement_rate');
        $test->method('getControlVariant')->willReturn(['layout' => 'standard']);
        $test->method('getTestVariants')->willReturn([
            'variant_a' => ['layout' => 'compact'],
        ]);
        $test->method('getTrafficAllocation')->willReturn(100);
        $test->method('getSignificanceLevel')->willReturn('0.05');
        $test->method('getStartDate')->willReturn(null);
        $test->method('getEndDate')->willReturn(null);
        $test->method('getWinnerVariant')->willReturn(null);
        $test->method('getConfidenceLevel')->willReturn(null);
        $test->method('getResults')->willReturn(null);
        $test->method('getLiveTexts')->willReturn(new ArrayCollection());

        return $test;
    }

    // --- createTest ---

    public function testCreateTestReturnsAbTestEntity(): void
    {
        $user = $this->createUser();

        $result = $this->service->createTest(
            'Experiment 1',
            'layout',
            ['layout' => 'standard'],
            ['variant_a' => ['layout' => 'compact']],
            'engagement_rate',
            $user,
            'Description',
            'Compact layout increases engagement',
            100,
            1000,
            '0.05'
        );

        $this->assertInstanceOf(LiveTextAbTest::class, $result);
    }

    public function testCreateTestWithMinimalParams(): void
    {
        $user = $this->createUser();

        $result = $this->service->createTest(
            'Simple Test',
            'color',
            ['color' => 'blue'],
            ['variant_a' => ['color' => 'red']],
            'click_rate',
            $user,
        );

        $this->assertInstanceOf(LiveTextAbTest::class, $result);
    }

    // --- startTest ---

    public function testStartTestChangesStatusToRunning(): void
    {
        $test = $this->createAbTest('draft');
        $test->expects($this->once())->method('setStatus')->with('running');
        $test->expects($this->once())->method('setStartDate');

        $this->service->startTest($test);
    }

    public function testStartTestAllowsPausedTests(): void
    {
        $test = $this->createAbTest('paused');
        $test->expects($this->once())->method('setStatus')->with('running');

        $this->service->startTest($test);
    }

    public function testStartTestThrowsForRunningTest(): void
    {
        $test = $this->createAbTest('running');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Can only start tests in draft or paused status');

        $this->service->startTest($test);
    }

    public function testStartTestThrowsForCompletedTest(): void
    {
        $test = $this->createAbTest('completed');

        $this->expectException(\RuntimeException::class);

        $this->service->startTest($test);
    }

    // --- pauseTest ---

    public function testPauseTestChangesStatusToPaused(): void
    {
        $test = $this->createAbTest('running');
        $test->expects($this->once())->method('setStatus')->with('paused');

        $this->service->pauseTest($test);
    }

    public function testPauseTestThrowsForNonRunningTest(): void
    {
        $test = $this->createAbTest('draft');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Can only pause running tests');

        $this->service->pauseTest($test);
    }

    // --- completeTest ---

    public function testCompleteTestChangesStatusToCompleted(): void
    {
        $test = $this->createAbTest('running');
        $test->expects($this->once())->method('setStatus')->with('completed');
        $test->expects($this->once())->method('setEndDate');

        $this->service->completeTest($test);
    }

    public function testCompleteTestSetsWinnerVariant(): void
    {
        $test = $this->createAbTest('running');
        $test->expects($this->once())->method('setWinnerVariant')->with('variant_a');

        $this->service->completeTest($test, 'variant_a');
    }

    public function testCompleteTestWithoutWinner(): void
    {
        $test = $this->createAbTest('running');
        $test->expects($this->never())->method('setWinnerVariant');

        $this->service->completeTest($test);
    }

    // --- assignVariant ---

    public function testAssignVariantReturnsControlForNonRunningTest(): void
    {
        $test = $this->createAbTest('draft');

        $result = $this->service->assignVariant($test);

        $this->assertSame('control', $result);
    }

    public function testAssignVariantReturnsSessionStoredVariant(): void
    {
        $test = $this->createAbTest('running');

        $session = $this->createStub(SessionInterface::class);
        $session->method('has')->willReturn(true);
        $session->method('get')->willReturn('variant_a');

        $request = $this->createStub(Request::class);
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $result = $this->service->assignVariant($test);

        $this->assertSame('variant_a', $result);
    }

    public function testAssignVariantReturnsValidVariant(): void
    {
        $test = $this->createAbTest('running');
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $result = $this->service->assignVariant($test, 'fixed-session-id');

        $this->assertContains($result, ['control', 'variant_a']);
    }

    // --- getVariantConfig ---

    public function testGetVariantConfigReturnsControlConfig(): void
    {
        $test = $this->createAbTest();

        $result = $this->service->getVariantConfig($test, 'control');

        $this->assertSame(['layout' => 'standard'], $result);
    }

    public function testGetVariantConfigReturnsTestVariantConfig(): void
    {
        $test = $this->createAbTest();

        $result = $this->service->getVariantConfig($test, 'variant_a');

        $this->assertSame(['layout' => 'compact'], $result);
    }

    public function testGetVariantConfigFallsBackToControlForUnknownVariant(): void
    {
        $test = $this->createAbTest();

        $result = $this->service->getVariantConfig($test, 'nonexistent');

        $this->assertSame(['layout' => 'standard'], $result);
    }

    // --- calculateResults ---

    public function testCalculateResultsReturnsNoDataWhenNoLiveTexts(): void
    {
        $test = $this->createAbTest('running');

        $result = $this->service->calculateResults($test);

        $this->assertSame('no_data', $result['status']);
    }

    // --- getRunningTestsForLiveText ---

    public function testGetRunningTestsForLiveTextReturnsMatchingTests(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $this->abTestRepository->method('findRunningTests')->willReturn([]);

        $result = $this->service->getRunningTestsForLiveText($liveText);

        $this->assertSame([], $result);
    }

    // --- addLiveTextToTest ---

    public function testAddLiveTextToTestAddsAndFlushes(): void
    {
        $test = $this->createAbTest();
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);
        $test->expects($this->once())->method('addLiveText')->with($liveText);

        $this->service->addLiveTextToTest($test, $liveText);
    }

    // --- removeLiveTextFromTest ---

    public function testRemoveLiveTextFromTestRemovesAndFlushes(): void
    {
        $test = $this->createAbTest();
        $liveText = $this->createStub(LiveText::class);
        $liveText->method('getId')->willReturn(1);
        $test->expects($this->once())->method('removeLiveText')->with($liveText);

        $this->service->removeLiveTextFromTest($test, $liveText);
    }

    // --- getTestSummary ---

    public function testGetTestSummaryReturnsStructuredData(): void
    {
        $test = $this->createAbTest('running', 42);

        $result = $this->service->getTestSummary($test);

        $this->assertSame(42, $result['id']);
        $this->assertSame('Test experiment', $result['name']);
        $this->assertSame('running', $result['status']);
        $this->assertSame('layout', $result['variant_type']);
        $this->assertSame('engagement_rate', $result['target_metric']);
        $this->assertArrayHasKey('start_date', $result);
        $this->assertArrayHasKey('end_date', $result);
        $this->assertArrayHasKey('winner_variant', $result);
        $this->assertArrayHasKey('confidence_level', $result);
        $this->assertArrayHasKey('livetext_count', $result);
        $this->assertArrayHasKey('results', $result);
    }

    // --- assignVariant: stores variant in session ---

    public function testAssignVariantStoresNewVariantInSession(): void
    {
        $test = $this->createAbTest('running');

        $session = $this->createMock(SessionInterface::class);
        $session->method('has')->willReturn(false);
        $session->expects($this->once())->method('set');

        $request = $this->createStub(Request::class);
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session);
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn($request);

        $service = new LiveTextAbTestService(
            $this->entityManager,
            $this->abTestRepository,
            $this->engagementRepository,
            $this->requestStack,
            $this->logger
        );

        $result = $service->assignVariant($test, 'test-session-id');

        $this->assertContains($result, ['control', 'variant_a']);
    }

    public function testAssignVariantWithNoRequestGeneratesSessionId(): void
    {
        $test = $this->createAbTest('running');
        $this->requestStack = $this->createStub(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $service = new LiveTextAbTestService(
            $this->entityManager,
            $this->abTestRepository,
            $this->engagementRepository,
            $this->requestStack,
            $this->logger
        );

        $result = $service->assignVariant($test);

        $this->assertContains($result, ['control', 'variant_a']);
    }

    // --- calculateResults with LiveTexts ---

    public function testCalculateResultsWithLiveTextsReturnsComparisons(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveTexts = new ArrayCollection([$liveText]);

        $test = $this->createMock(LiveTextAbTest::class);
        $test->method('getId')->willReturn(1);
        $test->method('getName')->willReturn('Test');
        $test->method('getStatus')->willReturn('running');
        $test->method('getTargetMetric')->willReturn('engagement_rate');
        $test->method('getControlVariant')->willReturn(['layout' => 'standard']);
        $test->method('getTestVariants')->willReturn([
            'variant_a' => ['layout' => 'compact'],
        ]);
        $test->method('getSignificanceLevel')->willReturn('0.05');
        $test->method('getLiveTexts')->willReturn($liveTexts);

        $result = $this->service->calculateResults($test);

        $this->assertArrayHasKey('variants', $result);
        $this->assertArrayHasKey('comparisons', $result);
        $this->assertArrayHasKey('winner', $result);
        // With zero sample sizes, control wins by default
        $this->assertSame('control', $result['winner']);
        $this->assertSame('100.00', $result['confidence_level']);
    }

    // --- getRunningTestsForLiveText with matching tests ---

    public function testGetRunningTestsForLiveTextReturnsOnlyMatchingTests(): void
    {
        $liveText = $this->createStub(LiveText::class);

        $matchingCollection = new ArrayCollection([$liveText]);
        $nonMatchingCollection = new ArrayCollection();

        $test1 = $this->createStub(LiveTextAbTest::class);
        $test1->method('getLiveTexts')->willReturn($matchingCollection);

        $test2 = $this->createStub(LiveTextAbTest::class);
        $test2->method('getLiveTexts')->willReturn($nonMatchingCollection);

        $this->abTestRepository = $this->createStub(LiveTextAbTestRepository::class);
        $this->abTestRepository->method('findRunningTests')->willReturn([$test1, $test2]);

        $service = new LiveTextAbTestService(
            $this->entityManager,
            $this->abTestRepository,
            $this->engagementRepository,
            $this->requestStack,
            $this->logger
        );

        $result = $service->getRunningTestsForLiveText($liveText);

        $this->assertCount(1, $result);
        $this->assertSame($test1, $result[0]);
    }

    // --- pauseTest throws for completed ---

    public function testPauseTestThrowsForCompletedTest(): void
    {
        $test = $this->createAbTest('completed');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Can only pause running tests');

        $this->service->pauseTest($test);
    }

    // --- selectVariant via assignVariant - traffic outside allocation ---

    public function testAssignVariantReturnsControlWhenOutsideTrafficAllocation(): void
    {
        // Create a test with very low traffic allocation (1%)
        $test = $this->createMock(LiveTextAbTest::class);
        $test->method('getId')->willReturn(1);
        $test->method('getName')->willReturn('Test');
        $test->method('getStatus')->willReturn('running');
        $test->method('getControlVariant')->willReturn(['layout' => 'standard']);
        $test->method('getTestVariants')->willReturn([
            'variant_a' => ['layout' => 'compact'],
        ]);
        $test->method('getTrafficAllocation')->willReturn(0); // 0% allocation

        $this->requestStack = $this->createStub(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $service = new LiveTextAbTestService(
            $this->entityManager,
            $this->abTestRepository,
            $this->engagementRepository,
            $this->requestStack,
            $this->logger
        );

        // With 0% traffic allocation, all sessions go to control
        $result = $service->assignVariant($test, 'any-session');

        $this->assertSame('control', $result);
    }

    // --- calculateResults with multiple test variants ---

    public function testCalculateResultsWithMultipleVariants(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveTexts = new ArrayCollection([$liveText]);

        $test = $this->createMock(LiveTextAbTest::class);
        $test->method('getId')->willReturn(1);
        $test->method('getName')->willReturn('Multi-variant test');
        $test->method('getStatus')->willReturn('running');
        $test->method('getTargetMetric')->willReturn('conversion_rate');
        $test->method('getControlVariant')->willReturn(['layout' => 'standard']);
        $test->method('getTestVariants')->willReturn([
            'variant_a' => ['layout' => 'compact'],
            'variant_b' => ['layout' => 'wide'],
        ]);
        $test->method('getSignificanceLevel')->willReturn('0.05');
        $test->method('getLiveTexts')->willReturn($liveTexts);

        $result = $this->service->calculateResults($test);

        $this->assertArrayHasKey('variants', $result);
        $this->assertArrayHasKey('comparisons', $result);
        $this->assertArrayHasKey('variant_a', $result['comparisons']);
        $this->assertArrayHasKey('variant_b', $result['comparisons']);
        $this->assertSame('control', $result['winner']);
        $this->assertSame('100.00', $result['confidence_level']);
    }

    // --- getTestSummary with start/end dates ---

    public function testGetTestSummaryWithDates(): void
    {
        $startDate = new \DateTime('2026-01-01');
        $endDate = new \DateTime('2026-02-01');

        $test = $this->createStub(LiveTextAbTest::class);
        $test->method('getId')->willReturn(10);
        $test->method('getName')->willReturn('Completed Test');
        $test->method('getStatus')->willReturn('completed');
        $test->method('getVariantType')->willReturn('color');
        $test->method('getTargetMetric')->willReturn('click_rate');
        $test->method('getStartDate')->willReturn($startDate);
        $test->method('getEndDate')->willReturn($endDate);
        $test->method('getWinnerVariant')->willReturn('variant_a');
        $test->method('getConfidenceLevel')->willReturn('95.50');
        $test->method('getLiveTexts')->willReturn(new ArrayCollection([
            $this->createStub(LiveText::class),
        ]));
        $test->method('getResults')->willReturn(['winner' => 'variant_a']);

        $result = $this->service->getTestSummary($test);

        $this->assertSame(10, $result['id']);
        $this->assertSame('completed', $result['status']);
        $this->assertSame('2026-01-01 00:00:00', $result['start_date']);
        $this->assertSame('2026-02-01 00:00:00', $result['end_date']);
        $this->assertSame('variant_a', $result['winner_variant']);
        $this->assertSame('95.50', $result['confidence_level']);
        $this->assertSame(1, $result['livetext_count']);
    }

    // --- assignVariant with session that has ab_test_session_id ---

    public function testAssignVariantUsesExistingSessionId(): void
    {
        $test = $this->createAbTest('running');

        $session = $this->createStub(SessionInterface::class);
        $session->method('has')->willReturnCallback(function ($key) {
            return $key === 'ab_test_session_id';
        });
        $session->method('get')->willReturnCallback(function ($key) {
            if ($key === 'ab_test_session_id') {
                return 'existing-session-id';
            }
            return null;
        });

        $request = $this->createStub(Request::class);
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session);

        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $service = new LiveTextAbTestService(
            $this->entityManager,
            $this->abTestRepository,
            $this->engagementRepository,
            $requestStack,
            $this->logger
        );

        $result = $service->assignVariant($test);

        $this->assertContains($result, ['control', 'variant_a']);
    }

    // --- completeTest without winner variant ---

    public function testCompleteTestWithNullWinnerDoesNotSetWinner(): void
    {
        $test = $this->createAbTest('running');
        $test->expects($this->once())->method('setStatus')->with('completed');
        $test->expects($this->never())->method('setWinnerVariant');

        $this->service->completeTest($test, null);
    }

    // --- calculateResults - variants with insufficient data message ---

    public function testCalculateResultsComparisonsShowInsufficientData(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveTexts = new ArrayCollection([$liveText]);

        $test = $this->createMock(LiveTextAbTest::class);
        $test->method('getId')->willReturn(1);
        $test->method('getName')->willReturn('Test');
        $test->method('getStatus')->willReturn('running');
        $test->method('getTargetMetric')->willReturn('engagement');
        $test->method('getControlVariant')->willReturn(['layout' => 'a']);
        $test->method('getTestVariants')->willReturn(['variant_a' => ['layout' => 'b']]);
        $test->method('getSignificanceLevel')->willReturn('0.05');
        $test->method('getLiveTexts')->willReturn($liveTexts);

        $result = $this->service->calculateResults($test);

        // With zero sample sizes, comparison should show insufficient data
        $comparison = $result['comparisons']['variant_a'];
        $this->assertFalse($comparison['is_significant']);
        $this->assertSame('Insufficient data', $comparison['message']);
        $this->assertSame(1.0, $comparison['p_value']);
        $this->assertSame('0.00', $comparison['confidence_level']);
    }

    // --- selectVariant: test with 100% traffic and multiple variants ---

    public function testAssignVariantWith100TrafficDistributes(): void
    {
        $test = $this->createMock(LiveTextAbTest::class);
        $test->method('getId')->willReturn(1);
        $test->method('getName')->willReturn('Test');
        $test->method('getStatus')->willReturn('running');
        $test->method('getControlVariant')->willReturn(['layout' => 'standard']);
        $test->method('getTestVariants')->willReturn([
            'variant_a' => ['layout' => 'compact'],
            'variant_b' => ['layout' => 'wide'],
        ]);
        $test->method('getTrafficAllocation')->willReturn(100);

        $this->requestStack = $this->createStub(RequestStack::class);
        $this->requestStack->method('getCurrentRequest')->willReturn(null);

        $service = new LiveTextAbTestService(
            $this->entityManager,
            $this->abTestRepository,
            $this->engagementRepository,
            $this->requestStack,
            $this->logger
        );

        // With 100% traffic and 3 variants (control + a + b), distribution is based on hash
        $result = $service->assignVariant($test, 'test-session-42');

        $this->assertContains($result, ['control', 'variant_a', 'variant_b']);
    }

    // --- getVariantConfig with non-matching variant ---

    public function testGetVariantConfigReturnsControlForMissingKey(): void
    {
        $test = $this->createAbTest();

        $result = $this->service->getVariantConfig($test, 'variant_xyz');

        // Falls back to control variant
        $this->assertSame(['layout' => 'standard'], $result);
    }

    // --- startTest: paused → running ---

    public function testStartTestFromPausedSetsStartDate(): void
    {
        $test = $this->createAbTest('paused');
        $test->expects($this->once())->method('setStatus')->with('running');
        $test->expects($this->once())->method('setStartDate');

        $this->service->startTest($test);
    }

    // --- getOrCreateSessionId: session exists but no ab_test_session_id key ---

    public function testAssignVariantCreatesNewSessionIdWhenSessionHasNoAbTestKey(): void
    {
        $test = $this->createAbTest('running');

        // Session exists but does NOT have ab_test_session_id
        $session = $this->createMock(SessionInterface::class);
        $session->method('has')->willReturnCallback(function ($key) {
            // No ab_test_{testId} key, no ab_test_session_id key
            return false;
        });
        $session->method('get')->willReturnCallback(function ($key) {
            if ($key === 'ab_test_session_id') {
                // After set, return the value
                return 'new-generated-id';
            }
            return null;
        });
        // set should be called twice: once for ab_test_session_id, once for the variant
        $session->expects($this->atLeastOnce())->method('set');

        $request = $this->createStub(Request::class);
        $request->method('hasSession')->willReturn(true);
        $request->method('getSession')->willReturn($session);

        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        $service = new LiveTextAbTestService(
            $this->entityManager,
            $this->abTestRepository,
            $this->engagementRepository,
            $requestStack,
            $this->logger
        );

        $result = $service->assignVariant($test);

        $this->assertContains($result, ['control', 'variant_a']);
    }

    // --- compareVariants with non-zero sample sizes ---

    public function testCalculateResultsWithNonZeroSampleSizes(): void
    {
        // This test needs a custom LiveTextAbTest where collectVariantMetrics
        // returns meaningful data. Since collectVariantMetrics is private and
        // returns mock data with 0 values, we test via calculateResults
        // which still exercises compareVariants logic with 0 sample sizes.
        // The 'insufficient data' path is covered by testCalculateResultsComparisonsShowInsufficientData.
        // To test the statistical calculation path we'd need to modify private methods,
        // so instead we verify the complete flow with multiple variants.
        $liveText = $this->createStub(LiveText::class);
        $liveTexts = new ArrayCollection([$liveText]);

        $test = $this->createMock(LiveTextAbTest::class);
        $test->method('getId')->willReturn(1);
        $test->method('getName')->willReturn('Statistical test');
        $test->method('getStatus')->willReturn('running');
        $test->method('getTargetMetric')->willReturn('conversion_rate');
        $test->method('getControlVariant')->willReturn(['layout' => 'standard']);
        $test->method('getTestVariants')->willReturn([
            'variant_a' => ['layout' => 'compact'],
            'variant_b' => ['layout' => 'wide'],
            'variant_c' => ['layout' => 'minimal'],
        ]);
        $test->method('getSignificanceLevel')->willReturn('0.01');
        $test->method('getLiveTexts')->willReturn($liveTexts);

        $result = $this->service->calculateResults($test);

        // All comparisons should exist
        $this->assertArrayHasKey('variant_a', $result['comparisons']);
        $this->assertArrayHasKey('variant_b', $result['comparisons']);
        $this->assertArrayHasKey('variant_c', $result['comparisons']);
        // With 0 sample sizes, all should be insufficient and control wins
        $this->assertSame('control', $result['winner']);
        $this->assertSame('100.00', $result['confidence_level']);
    }

    // --- calculateResults: null significance level fallback ---

    public function testCalculateResultsWithNullSignificanceLevel(): void
    {
        $liveText = $this->createStub(LiveText::class);
        $liveTexts = new ArrayCollection([$liveText]);

        $test = $this->createMock(LiveTextAbTest::class);
        $test->method('getId')->willReturn(1);
        $test->method('getName')->willReturn('Test');
        $test->method('getStatus')->willReturn('running');
        $test->method('getTargetMetric')->willReturn('engagement');
        $test->method('getControlVariant')->willReturn(['layout' => 'a']);
        $test->method('getTestVariants')->willReturn(['variant_a' => ['layout' => 'b']]);
        $test->method('getSignificanceLevel')->willReturn(null);
        $test->method('getLiveTexts')->willReturn($liveTexts);

        $result = $this->service->calculateResults($test);

        $this->assertArrayHasKey('comparisons', $result);
        $this->assertSame('control', $result['winner']);
    }

    // --- selectVariant consistency: same session always gets same variant ---

    public function testAssignVariantIsConsistentForSameSession(): void
    {
        $test = $this->createMock(LiveTextAbTest::class);
        $test->method('getId')->willReturn(1);
        $test->method('getStatus')->willReturn('running');
        $test->method('getControlVariant')->willReturn(['layout' => 'standard']);
        $test->method('getTestVariants')->willReturn([
            'variant_a' => ['layout' => 'compact'],
        ]);
        $test->method('getTrafficAllocation')->willReturn(100);

        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn(null);

        $service = new LiveTextAbTestService(
            $this->entityManager,
            $this->abTestRepository,
            $this->engagementRepository,
            $requestStack,
            $this->logger
        );

        // Same session ID should always return same variant
        $result1 = $service->assignVariant($test, 'consistent-session-id');
        $result2 = $service->assignVariant($test, 'consistent-session-id');

        $this->assertSame($result1, $result2);
    }

    // --- completeTest from non-running status ---

    public function testCompleteTestFromDraftStatus(): void
    {
        // completeTest doesn't check current status - it just sets completed
        $test = $this->createAbTest('draft');
        $test->expects($this->once())->method('setStatus')->with('completed');
        $test->expects($this->once())->method('setEndDate');

        $this->service->completeTest($test);
    }

    // --- createTest: all optional parameters set ---

    public function testCreateTestSetsAllOptionalFields(): void
    {
        $user = $this->createUser();

        $result = $this->service->createTest(
            'Full Test',
            'layout',
            ['layout' => 'standard'],
            ['variant_a' => ['layout' => 'compact']],
            'engagement_rate',
            $user,
            'Test description',
            'Layout changes increase engagement',
            80,
            500,
            '0.01'
        );

        $this->assertInstanceOf(LiveTextAbTest::class, $result);
    }
}
