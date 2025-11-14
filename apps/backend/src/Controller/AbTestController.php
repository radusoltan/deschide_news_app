<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LiveText;
use App\Entity\LiveTextAbTest;
use App\Entity\User;
use App\Service\LiveTextAbTestService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/ab-tests')]
#[IsGranted('ROLE_EDITOR')]
class AbTestController extends AbstractController
{
    public function __construct(
        private LiveTextAbTestService $abTestService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Create a new A/B test
     * POST /api/ab-tests.
     */
    #[Route('', name: 'ab_test_create', methods: ['POST'])]
    public function createTest(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['name'], $data['variant_type'], $data['control_variant'], $data['test_variants'], $data['target_metric'])) {
            return $this->json([
                'error' => 'Missing required fields: name, variant_type, control_variant, test_variants, target_metric',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'error' => 'User not authenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $test = $this->abTestService->createTest(
                $data['name'],
                $data['variant_type'],
                $data['control_variant'],
                $data['test_variants'],
                $data['target_metric'],
                $user,
                $data['description'] ?? null,
                $data['hypothesis'] ?? null,
                $data['traffic_allocation'] ?? 100,
                $data['min_sample_size'] ?? null,
                $data['significance_level'] ?? '0.05'
            );

            return $this->json([
                'success' => true,
                'test' => $this->abTestService->getTestSummary($test),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->json([
                'error' => 'Failed to create test: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List all A/B tests
     * GET /api/ab-tests.
     */
    #[Route('', name: 'ab_test_list', methods: ['GET'])]
    public function listTests(Request $request): JsonResponse
    {
        $status = $request->query->get('status');
        $repository = $this->entityManager->getRepository(LiveTextAbTest::class);

        if ($status) {
            $tests = $repository->findByStatus($status);
        } else {
            $tests = $repository->findAll();
        }

        $testsData = array_map(
            fn (LiveTextAbTest $test) => $this->abTestService->getTestSummary($test),
            $tests
        );

        return $this->json([
            'tests' => $testsData,
            'total' => \count($testsData),
        ]);
    }

    /**
     * Get test details
     * GET /api/ab-tests/{id}.
     */
    #[Route('/{id}', name: 'ab_test_get', methods: ['GET'])]
    public function getTest(int $id): JsonResponse
    {
        $test = $this->entityManager->getRepository(LiveTextAbTest::class)->find($id);
        if (!$test) {
            return $this->json([
                'error' => 'Test not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $summary = $this->abTestService->getTestSummary($test);

        // Add LiveTexts info
        $liveTexts = [];
        foreach ($test->getLiveTexts() as $liveText) {
            $liveTexts[] = [
                'id' => $liveText->getId(),
                'title' => $liveText->getTitle(),
                'slug' => $liveText->getSlug(),
                'status' => $liveText->getStatus()->value,
            ];
        }
        $summary['live_texts'] = $liveTexts;

        return $this->json($summary);
    }

    /**
     * Start an A/B test
     * PUT /api/ab-tests/{id}/start.
     */
    #[Route('/{id}/start', name: 'ab_test_start', methods: ['PUT'])]
    public function startTest(int $id): JsonResponse
    {
        $test = $this->entityManager->getRepository(LiveTextAbTest::class)->find($id);
        if (!$test) {
            return $this->json([
                'error' => 'Test not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->abTestService->startTest($test);

            return $this->json([
                'success' => true,
                'test' => $this->abTestService->getTestSummary($test),
            ]);
        } catch (Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Pause an A/B test
     * PUT /api/ab-tests/{id}/pause.
     */
    #[Route('/{id}/pause', name: 'ab_test_pause', methods: ['PUT'])]
    public function pauseTest(int $id): JsonResponse
    {
        $test = $this->entityManager->getRepository(LiveTextAbTest::class)->find($id);
        if (!$test) {
            return $this->json([
                'error' => 'Test not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->abTestService->pauseTest($test);

            return $this->json([
                'success' => true,
                'test' => $this->abTestService->getTestSummary($test),
            ]);
        } catch (Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Complete an A/B test
     * PUT /api/ab-tests/{id}/complete.
     */
    #[Route('/{id}/complete', name: 'ab_test_complete', methods: ['PUT'])]
    public function completeTest(int $id, Request $request): JsonResponse
    {
        $test = $this->entityManager->getRepository(LiveTextAbTest::class)->find($id);
        if (!$test) {
            return $this->json([
                'error' => 'Test not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $winnerVariant = $data['winner_variant'] ?? null;

        try {
            $this->abTestService->completeTest($test, $winnerVariant);

            return $this->json([
                'success' => true,
                'test' => $this->abTestService->getTestSummary($test),
            ]);
        } catch (Exception $e) {
            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get assigned variant for current session
     * GET /api/ab-tests/{id}/variant.
     */
    #[Route('/{id}/variant', name: 'ab_test_get_variant', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function getVariant(int $id): JsonResponse
    {
        $test = $this->entityManager->getRepository(LiveTextAbTest::class)->find($id);
        if (!$test) {
            return $this->json([
                'error' => 'Test not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $variantKey = $this->abTestService->assignVariant($test);
        $variantConfig = $this->abTestService->getVariantConfig($test, $variantKey);

        return $this->json([
            'variant' => $variantKey,
            'config' => $variantConfig,
            'test_id' => $test->getId(),
            'test_name' => $test->getName(),
        ]);
    }

    /**
     * Calculate test results
     * POST /api/ab-tests/{id}/calculate.
     */
    #[Route('/{id}/calculate', name: 'ab_test_calculate', methods: ['POST'])]
    public function calculateResults(int $id): JsonResponse
    {
        $test = $this->entityManager->getRepository(LiveTextAbTest::class)->find($id);
        if (!$test) {
            return $this->json([
                'error' => 'Test not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $results = $this->abTestService->calculateResults($test);

            return $this->json([
                'success' => true,
                'results' => $results,
            ]);
        } catch (Exception $e) {
            return $this->json([
                'error' => 'Failed to calculate results: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Add LiveText to test
     * POST /api/ab-tests/{id}/live-texts.
     */
    #[Route('/{id}/live-texts', name: 'ab_test_add_livetext', methods: ['POST'])]
    public function addLiveText(int $id, Request $request): JsonResponse
    {
        $test = $this->entityManager->getRepository(LiveTextAbTest::class)->find($id);
        if (!$test) {
            return $this->json([
                'error' => 'Test not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['livetext_id'])) {
            return $this->json([
                'error' => 'Missing required field: livetext_id',
            ], Response::HTTP_BAD_REQUEST);
        }

        $liveText = $this->entityManager->getRepository(LiveText::class)->find($data['livetext_id']);
        if (!$liveText) {
            return $this->json([
                'error' => 'LiveText not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->abTestService->addLiveTextToTest($test, $liveText);

        return $this->json([
            'success' => true,
            'test' => $this->abTestService->getTestSummary($test),
        ]);
    }

    /**
     * Remove LiveText from test
     * DELETE /api/ab-tests/{id}/live-texts/{liveTextId}.
     */
    #[Route('/{id}/live-texts/{liveTextId}', name: 'ab_test_remove_livetext', methods: ['DELETE'])]
    public function removeLiveText(int $id, int $liveTextId): JsonResponse
    {
        $test = $this->entityManager->getRepository(LiveTextAbTest::class)->find($id);
        if (!$test) {
            return $this->json([
                'error' => 'Test not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $liveText = $this->entityManager->getRepository(LiveText::class)->find($liveTextId);
        if (!$liveText) {
            return $this->json([
                'error' => 'LiveText not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->abTestService->removeLiveTextFromTest($test, $liveText);

        return $this->json([
            'success' => true,
            'test' => $this->abTestService->getTestSummary($test),
        ]);
    }

    /**
     * Get running tests for a LiveText
     * GET /api/ab-tests/live-text/{liveTextId}.
     */
    #[Route('/live-text/{liveTextId}', name: 'ab_test_get_by_livetext', methods: ['GET'])]
    public function getTestsByLiveText(int $liveTextId): JsonResponse
    {
        $liveText = $this->entityManager->getRepository(LiveText::class)->find($liveTextId);
        if (!$liveText) {
            return $this->json([
                'error' => 'LiveText not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $tests = $this->abTestService->getRunningTestsForLiveText($liveText);

        $testsData = array_map(
            fn (LiveTextAbTest $test) => $this->abTestService->getTestSummary($test),
            $tests
        );

        return $this->json([
            'tests' => $testsData,
            'total' => \count($testsData),
            'livetext_id' => $liveTextId,
        ]);
    }
}
