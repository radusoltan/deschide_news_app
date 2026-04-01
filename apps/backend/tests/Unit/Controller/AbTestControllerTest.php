<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\AbTestController;
use App\Entity\LiveText;
use App\Entity\LiveTextAbTest;
use App\Entity\User;
use App\Enum\LiveTextStatus;
use App\Repository\LiveTextAbTestRepository;
use App\Service\LiveTextAbTestService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Unit tests for AbTestController.
 *
 * Tests all public methods: createTest, listTests, getTest, startTest,
 * pauseTest, completeTest, getVariant, calculateResults, addLiveText,
 * removeLiveText, getTestsByLiveText.
 */
class AbTestControllerTest extends TestCase
{
    private LiveTextAbTestService $abTestService;
    private EntityManagerInterface $entityManager;
    private AbTestController $controller;
    private LiveTextAbTestRepository $abTestRepository;
    private EntityRepository $liveTextRepository;

    protected function setUp(): void
    {
        $this->abTestService = $this->createStub(LiveTextAbTestService::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->abTestRepository = $this->createStub(LiveTextAbTestRepository::class);
        $this->liveTextRepository = $this->createStub(EntityRepository::class);

        $this->entityManager->method('getRepository')->willReturnCallback(
            function (string $entityClass) {
                return match ($entityClass) {
                    LiveTextAbTest::class => $this->abTestRepository,
                    LiveText::class => $this->liveTextRepository,
                    default => $this->createStub(EntityRepository::class),
                };
            }
        );

        $this->controller = new AbTestController($this->abTestService, $this->entityManager);

        // Set up the container for AbstractController methods (json(), getUser())
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function (string $id): bool {
            return match ($id) {
                'parameter_bag', 'security.token_storage' => true,
                default => false,
            };
        });

        $paramBag = $this->createStub(ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([
            ['kernel.debug', false],
        ]);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);

        $container->method('get')->willReturnCallback(
            function (string $id) use ($paramBag, $tokenStorage) {
                return match ($id) {
                    'parameter_bag' => $paramBag,
                    'security.token_storage' => $tokenStorage,
                    default => null,
                };
            }
        );

        $this->controller->setContainer($container);
    }

    // =============================================
    // Helper methods
    // =============================================

    private function createAbTest(int $id = 1, string $name = 'Test A/B', string $status = 'draft'): LiveTextAbTest
    {
        $test = new LiveTextAbTest();
        $test->setName($name);
        $test->setStatus($status);
        $test->setVariantType('template');
        $test->setControlVariant(['key' => 'control']);
        $test->setTestVariants(['variant_a' => ['key' => 'variant_a']]);
        $test->setTargetMetric('engagement_rate');

        // Use reflection to set the ID
        $reflection = new \ReflectionClass($test);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($test, $id);

        return $test;
    }

    private function createLiveText(int $id = 1, string $title = 'Live Text', string $slug = 'live-text'): LiveText
    {
        $liveText = new LiveText();
        $liveText->setTitle($title);
        $liveText->setSlug($slug);
        $liveText->setStatus(LiveTextStatus::LIVE);

        $reflection = new \ReflectionClass($liveText);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($liveText, $id);

        return $liveText;
    }

    private function createUser(int $id = 1): User
    {
        $user = new User();

        $reflection = new \ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setValue($user, $id);

        return $user;
    }

    private function setUpControllerWithUser(?User $user): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $paramBag = $this->createStub(ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([
            ['kernel.debug', false],
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function (string $id): bool {
            return match ($id) {
                'parameter_bag', 'security.token_storage' => true,
                default => false,
            };
        });
        $container->method('get')->willReturnCallback(
            function (string $id) use ($paramBag, $tokenStorage) {
                return match ($id) {
                    'parameter_bag' => $paramBag,
                    'security.token_storage' => $tokenStorage,
                    default => null,
                };
            }
        );

        $this->controller->setContainer($container);
    }

    private function getJsonContent(\Symfony\Component\HttpFoundation\JsonResponse $response): array
    {
        return json_decode($response->getContent(), true);
    }

    // =============================================
    // POST /api/ab-tests (createTest)
    // =============================================

    #[Test]
    public function createTestReturnsBadRequestWhenMissingRequiredFields(): void
    {
        $this->setUpControllerWithUser($this->createUser());

        $request = Request::create('/api/ab-tests', 'POST', [], [], [], [], json_encode([
            'name' => 'My Test',
            // missing variant_type, control_variant, test_variants, target_metric
        ]));

        $response = $this->controller->createTest($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('Missing required fields', $data['error']);
    }

    #[Test]
    public function createTestReturnsBadRequestWhenMissingName(): void
    {
        $this->setUpControllerWithUser($this->createUser());

        $request = Request::create('/api/ab-tests', 'POST', [], [], [], [], json_encode([
            'variant_type' => 'template',
            'control_variant' => ['key' => 'control'],
            'test_variants' => ['variant_a' => ['key' => 'a']],
            'target_metric' => 'views',
        ]));

        $response = $this->controller->createTest($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function createTestReturnsBadRequestWhenBodyIsEmpty(): void
    {
        $this->setUpControllerWithUser($this->createUser());

        $request = Request::create('/api/ab-tests', 'POST', [], [], [], [], '{}');

        $response = $this->controller->createTest($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function createTestReturnsUnauthorizedWhenUserNotAuthenticated(): void
    {
        // Set up controller with no user (null token)
        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $paramBag = $this->createStub(ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([
            ['kernel.debug', false],
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function (string $id): bool {
            return match ($id) {
                'parameter_bag', 'security.token_storage' => true,
                default => false,
            };
        });
        $container->method('get')->willReturnCallback(
            function (string $id) use ($paramBag, $tokenStorage) {
                return match ($id) {
                    'parameter_bag' => $paramBag,
                    'security.token_storage' => $tokenStorage,
                    default => null,
                };
            }
        );
        $this->controller->setContainer($container);

        $request = Request::create('/api/ab-tests', 'POST', [], [], [], [], json_encode([
            'name' => 'My Test',
            'variant_type' => 'template',
            'control_variant' => ['key' => 'control'],
            'test_variants' => ['variant_a' => ['key' => 'a']],
            'target_metric' => 'views',
        ]));

        $response = $this->controller->createTest($request);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('User not authenticated', $data['error']);
    }

    #[Test]
    public function createTestReturnsCreatedOnSuccess(): void
    {
        $user = $this->createUser();
        $this->setUpControllerWithUser($user);

        $abTest = $this->createAbTest(1, 'My Test');
        $summary = ['id' => 1, 'name' => 'My Test', 'status' => 'draft'];

        $this->abTestService = $this->createStub(LiveTextAbTestService::class);
        $this->abTestService->method('createTest')->willReturn($abTest);
        $this->abTestService->method('getTestSummary')->willReturn($summary);

        // Rebuild the controller with the updated service
        $this->controller = new AbTestController($this->abTestService, $this->entityManager);
        $this->setUpControllerWithUser($user);

        $request = Request::create('/api/ab-tests', 'POST', [], [], [], [], json_encode([
            'name' => 'My Test',
            'variant_type' => 'template',
            'control_variant' => ['key' => 'control'],
            'test_variants' => ['variant_a' => ['key' => 'a']],
            'target_metric' => 'views',
        ]));

        $response = $this->controller->createTest($request);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertTrue($data['success']);
        $this->assertSame($summary, $data['test']);
    }

    #[Test]
    public function createTestPassesOptionalFieldsToService(): void
    {
        $user = $this->createUser();
        $this->setUpControllerWithUser($user);

        $abTest = $this->createAbTest(1);
        $summary = ['id' => 1, 'name' => 'My Test'];

        $mock = $this->createMock(LiveTextAbTestService::class);
        $mock->expects($this->once())
            ->method('createTest')
            ->with(
                'My Test',
                'template',
                ['key' => 'control'],
                ['variant_a' => ['key' => 'a']],
                'views',
                $user,
                'A description',
                'Testing hypothesis',
                80,
                1000,
                '0.01'
            )
            ->willReturn($abTest);
        $mock->method('getTestSummary')->willReturn($summary);

        $this->controller = new AbTestController($mock, $this->entityManager);
        $this->setUpControllerWithUser($user);

        $request = Request::create('/api/ab-tests', 'POST', [], [], [], [], json_encode([
            'name' => 'My Test',
            'variant_type' => 'template',
            'control_variant' => ['key' => 'control'],
            'test_variants' => ['variant_a' => ['key' => 'a']],
            'target_metric' => 'views',
            'description' => 'A description',
            'hypothesis' => 'Testing hypothesis',
            'traffic_allocation' => 80,
            'min_sample_size' => 1000,
            'significance_level' => '0.01',
        ]));

        $response = $this->controller->createTest($request);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    #[Test]
    public function createTestUsesDefaultsForOptionalFields(): void
    {
        $user = $this->createUser();
        $this->setUpControllerWithUser($user);

        $abTest = $this->createAbTest(1);
        $summary = ['id' => 1];

        $mock = $this->createMock(LiveTextAbTestService::class);
        $mock->expects($this->once())
            ->method('createTest')
            ->with(
                'My Test',
                'template',
                ['key' => 'control'],
                ['variant_a' => ['key' => 'a']],
                'views',
                $user,
                null,       // description default
                null,       // hypothesis default
                100,        // traffic_allocation default
                null,       // min_sample_size default
                '0.05'      // significance_level default
            )
            ->willReturn($abTest);
        $mock->method('getTestSummary')->willReturn($summary);

        $this->controller = new AbTestController($mock, $this->entityManager);
        $this->setUpControllerWithUser($user);

        $request = Request::create('/api/ab-tests', 'POST', [], [], [], [], json_encode([
            'name' => 'My Test',
            'variant_type' => 'template',
            'control_variant' => ['key' => 'control'],
            'test_variants' => ['variant_a' => ['key' => 'a']],
            'target_metric' => 'views',
        ]));

        $response = $this->controller->createTest($request);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());
    }

    #[Test]
    public function createTestReturnsServerErrorWhenServiceThrows(): void
    {
        $user = $this->createUser();
        $this->setUpControllerWithUser($user);

        $this->abTestService = $this->createStub(LiveTextAbTestService::class);
        $this->abTestService->method('createTest')->willThrowException(new Exception('DB connection failed'));

        $this->controller = new AbTestController($this->abTestService, $this->entityManager);
        $this->setUpControllerWithUser($user);

        $request = Request::create('/api/ab-tests', 'POST', [], [], [], [], json_encode([
            'name' => 'My Test',
            'variant_type' => 'template',
            'control_variant' => ['key' => 'control'],
            'test_variants' => ['variant_a' => ['key' => 'a']],
            'target_metric' => 'views',
        ]));

        $response = $this->controller->createTest($request);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertStringContainsString('Failed to create test', $data['error']);
        $this->assertStringContainsString('DB connection failed', $data['error']);
    }

    // =============================================
    // GET /api/ab-tests (listTests)
    // =============================================

    #[Test]
    public function listTestsReturnsAllTestsWhenNoStatusFilter(): void
    {
        $test1 = $this->createAbTest(1, 'Test 1', 'draft');
        $test2 = $this->createAbTest(2, 'Test 2', 'running');

        $this->abTestRepository->method('findAll')->willReturn([$test1, $test2]);
        $this->abTestService->method('getTestSummary')->willReturnCallback(
            fn (LiveTextAbTest $t) => ['id' => $t->getId(), 'name' => $t->getName()]
        );

        $request = Request::create('/api/ab-tests', 'GET');

        $response = $this->controller->listTests($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['tests']);
    }

    #[Test]
    public function listTestsFiltersbyStatusWhenProvided(): void
    {
        $test = $this->createAbTest(1, 'Running Test', 'running');

        $this->abTestRepository->method('findByStatus')->willReturn([$test]);
        $this->abTestService->method('getTestSummary')->willReturn(
            ['id' => 1, 'name' => 'Running Test', 'status' => 'running']
        );

        $request = Request::create('/api/ab-tests', 'GET', ['status' => 'running']);

        $response = $this->controller->listTests($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame(1, $data['total']);
        $this->assertCount(1, $data['tests']);
        $this->assertSame('Running Test', $data['tests'][0]['name']);
    }

    #[Test]
    public function listTestsReturnsEmptyArrayWhenNoTests(): void
    {
        $this->abTestRepository->method('findAll')->willReturn([]);

        $request = Request::create('/api/ab-tests', 'GET');

        $response = $this->controller->listTests($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame(0, $data['total']);
        $this->assertEmpty($data['tests']);
    }

    // =============================================
    // GET /api/ab-tests/{id} (getTest)
    // =============================================

    #[Test]
    public function getTestReturnsNotFoundWhenTestDoesNotExist(): void
    {
        $this->abTestRepository->method('find')->willReturn(null);

        $response = $this->controller->getTest(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('Test not found', $data['error']);
    }

    #[Test]
    public function getTestReturnsTestWithLiveTexts(): void
    {
        $test = $this->createAbTest(1, 'My Test', 'running');
        $liveText = $this->createLiveText(10, 'Breaking News', 'breaking-news');
        $test->addLiveText($liveText);

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService->method('getTestSummary')->willReturn([
            'id' => 1,
            'name' => 'My Test',
            'status' => 'running',
        ]);

        $response = $this->controller->getTest(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame(1, $data['id']);
        $this->assertSame('My Test', $data['name']);
        $this->assertArrayHasKey('live_texts', $data);
        $this->assertCount(1, $data['live_texts']);
        $this->assertSame(10, $data['live_texts'][0]['id']);
        $this->assertSame('Breaking News', $data['live_texts'][0]['title']);
        $this->assertSame('breaking-news', $data['live_texts'][0]['slug']);
        $this->assertSame('live', $data['live_texts'][0]['status']);
    }

    #[Test]
    public function getTestReturnsEmptyLiveTextsWhenNoneAssociated(): void
    {
        $test = $this->createAbTest(1, 'My Test');

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService->method('getTestSummary')->willReturn([
            'id' => 1,
            'name' => 'My Test',
        ]);

        $response = $this->controller->getTest(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertEmpty($data['live_texts']);
    }

    // =============================================
    // PUT /api/ab-tests/{id}/start (startTest)
    // =============================================

    #[Test]
    public function startTestReturnsNotFoundWhenTestDoesNotExist(): void
    {
        $this->abTestRepository->method('find')->willReturn(null);

        $response = $this->controller->startTest(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('Test not found', $data['error']);
    }

    #[Test]
    public function startTestReturnsSuccessOnSuccess(): void
    {
        $test = $this->createAbTest(1, 'My Test', 'draft');
        $summary = ['id' => 1, 'name' => 'My Test', 'status' => 'running'];

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService->method('getTestSummary')->willReturn($summary);

        $response = $this->controller->startTest(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertTrue($data['success']);
        $this->assertSame($summary, $data['test']);
    }

    #[Test]
    public function startTestReturnsBadRequestWhenServiceThrows(): void
    {
        $test = $this->createAbTest(1, 'My Test', 'completed');

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService = $this->createStub(LiveTextAbTestService::class);
        $this->abTestService->method('startTest')->willThrowException(
            new Exception('Can only start tests in draft or paused status')
        );

        $this->controller = new AbTestController($this->abTestService, $this->entityManager);
        $this->setUpControllerWithUser($this->createUser());

        $response = $this->controller->startTest(1);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertStringContainsString('Can only start tests', $data['error']);
    }

    // =============================================
    // PUT /api/ab-tests/{id}/pause (pauseTest)
    // =============================================

    #[Test]
    public function pauseTestReturnsNotFoundWhenTestDoesNotExist(): void
    {
        $this->abTestRepository->method('find')->willReturn(null);

        $response = $this->controller->pauseTest(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('Test not found', $data['error']);
    }

    #[Test]
    public function pauseTestReturnsSuccessOnSuccess(): void
    {
        $test = $this->createAbTest(1, 'My Test', 'running');
        $summary = ['id' => 1, 'name' => 'My Test', 'status' => 'paused'];

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService->method('getTestSummary')->willReturn($summary);

        $response = $this->controller->pauseTest(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertTrue($data['success']);
        $this->assertSame($summary, $data['test']);
    }

    #[Test]
    public function pauseTestReturnsBadRequestWhenServiceThrows(): void
    {
        $test = $this->createAbTest(1, 'My Test', 'draft');

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService = $this->createStub(LiveTextAbTestService::class);
        $this->abTestService->method('pauseTest')->willThrowException(
            new Exception('Can only pause running tests')
        );

        $this->controller = new AbTestController($this->abTestService, $this->entityManager);
        $this->setUpControllerWithUser($this->createUser());

        $response = $this->controller->pauseTest(1);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertStringContainsString('Can only pause running tests', $data['error']);
    }

    // =============================================
    // PUT /api/ab-tests/{id}/complete (completeTest)
    // =============================================

    #[Test]
    public function completeTestReturnsNotFoundWhenTestDoesNotExist(): void
    {
        $this->abTestRepository->method('find')->willReturn(null);

        $request = Request::create('/api/ab-tests/999/complete', 'PUT');

        $response = $this->controller->completeTest(999, $request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('Test not found', $data['error']);
    }

    #[Test]
    public function completeTestReturnsSuccessWithWinnerVariant(): void
    {
        $test = $this->createAbTest(1, 'My Test', 'running');
        $summary = ['id' => 1, 'name' => 'My Test', 'status' => 'completed', 'winner_variant' => 'variant_a'];

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService->method('getTestSummary')->willReturn($summary);

        $request = Request::create('/api/ab-tests/1/complete', 'PUT', [], [], [], [], json_encode([
            'winner_variant' => 'variant_a',
        ]));

        $response = $this->controller->completeTest(1, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertTrue($data['success']);
        $this->assertSame('variant_a', $data['test']['winner_variant']);
    }

    #[Test]
    public function completeTestReturnsSuccessWithoutWinnerVariant(): void
    {
        $test = $this->createAbTest(1, 'My Test', 'running');
        $summary = ['id' => 1, 'status' => 'completed'];

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService->method('getTestSummary')->willReturn($summary);

        $request = Request::create('/api/ab-tests/1/complete', 'PUT', [], [], [], [], '{}');

        $response = $this->controller->completeTest(1, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertTrue($data['success']);
    }

    #[Test]
    public function completeTestReturnsBadRequestWhenServiceThrows(): void
    {
        $test = $this->createAbTest(1);

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService = $this->createStub(LiveTextAbTestService::class);
        $this->abTestService->method('completeTest')->willThrowException(
            new Exception('Test already completed')
        );

        $this->controller = new AbTestController($this->abTestService, $this->entityManager);
        $this->setUpControllerWithUser($this->createUser());

        $request = Request::create('/api/ab-tests/1/complete', 'PUT', [], [], [], [], '{}');

        $response = $this->controller->completeTest(1, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertStringContainsString('Test already completed', $data['error']);
    }

    // =============================================
    // GET /api/ab-tests/{id}/variant (getVariant)
    // =============================================

    #[Test]
    public function getVariantReturnsNotFoundWhenTestDoesNotExist(): void
    {
        $this->abTestRepository->method('find')->willReturn(null);

        $response = $this->controller->getVariant(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('Test not found', $data['error']);
    }

    #[Test]
    public function getVariantReturnsVariantAndConfig(): void
    {
        $test = $this->createAbTest(5, 'My Test', 'running');

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService->method('assignVariant')->willReturn('variant_a');
        $this->abTestService->method('getVariantConfig')->willReturn(['template' => 'dark']);

        $response = $this->controller->getVariant(5);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('variant_a', $data['variant']);
        $this->assertSame(['template' => 'dark'], $data['config']);
        $this->assertSame(5, $data['test_id']);
        $this->assertSame('My Test', $data['test_name']);
    }

    #[Test]
    public function getVariantReturnsControlVariant(): void
    {
        $test = $this->createAbTest(3, 'Control Test', 'running');

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService->method('assignVariant')->willReturn('control');
        $this->abTestService->method('getVariantConfig')->willReturn(['key' => 'control']);

        $response = $this->controller->getVariant(3);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('control', $data['variant']);
        $this->assertSame(['key' => 'control'], $data['config']);
    }

    // =============================================
    // POST /api/ab-tests/{id}/calculate (calculateResults)
    // =============================================

    #[Test]
    public function calculateResultsReturnsNotFoundWhenTestDoesNotExist(): void
    {
        $this->abTestRepository->method('find')->willReturn(null);

        $response = $this->controller->calculateResults(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('Test not found', $data['error']);
    }

    #[Test]
    public function calculateResultsReturnsSuccessWithResults(): void
    {
        $test = $this->createAbTest(1, 'My Test', 'running');
        $results = [
            'variants' => ['control' => ['sample_size' => 100]],
            'winner' => 'variant_a',
            'confidence_level' => '95.50',
        ];

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService->method('calculateResults')->willReturn($results);

        $response = $this->controller->calculateResults(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertTrue($data['success']);
        $this->assertSame($results, $data['results']);
        $this->assertSame('variant_a', $data['results']['winner']);
    }

    #[Test]
    public function calculateResultsReturnsServerErrorWhenServiceThrows(): void
    {
        $test = $this->createAbTest(1);

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService = $this->createStub(LiveTextAbTestService::class);
        $this->abTestService->method('calculateResults')->willThrowException(
            new Exception('Division by zero')
        );

        $this->controller = new AbTestController($this->abTestService, $this->entityManager);
        $this->setUpControllerWithUser($this->createUser());

        $response = $this->controller->calculateResults(1);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertStringContainsString('Failed to calculate results', $data['error']);
        $this->assertStringContainsString('Division by zero', $data['error']);
    }

    // =============================================
    // POST /api/ab-tests/{id}/live-texts (addLiveText)
    // =============================================

    #[Test]
    public function addLiveTextReturnsNotFoundWhenTestDoesNotExist(): void
    {
        $this->abTestRepository->method('find')->willReturn(null);

        $request = Request::create('/api/ab-tests/999/live-texts', 'POST', [], [], [], [], json_encode([
            'livetext_id' => 1,
        ]));

        $response = $this->controller->addLiveText(999, $request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('Test not found', $data['error']);
    }

    #[Test]
    public function addLiveTextReturnsBadRequestWhenLiveTextIdMissing(): void
    {
        $test = $this->createAbTest(1);
        $this->abTestRepository->method('find')->willReturn($test);

        $request = Request::create('/api/ab-tests/1/live-texts', 'POST', [], [], [], [], '{}');

        $response = $this->controller->addLiveText(1, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertStringContainsString('Missing required field: livetext_id', $data['error']);
    }

    #[Test]
    public function addLiveTextReturnsNotFoundWhenLiveTextDoesNotExist(): void
    {
        $test = $this->createAbTest(1);
        $this->abTestRepository->method('find')->willReturn($test);
        $this->liveTextRepository->method('find')->willReturn(null);

        $request = Request::create('/api/ab-tests/1/live-texts', 'POST', [], [], [], [], json_encode([
            'livetext_id' => 999,
        ]));

        $response = $this->controller->addLiveText(1, $request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('LiveText not found', $data['error']);
    }

    #[Test]
    public function addLiveTextReturnsSuccessOnSuccess(): void
    {
        $test = $this->createAbTest(1);
        $liveText = $this->createLiveText(10);
        $summary = ['id' => 1, 'name' => 'My Test', 'livetext_count' => 1];

        $this->abTestRepository->method('find')->willReturn($test);
        $this->liveTextRepository->method('find')->willReturn($liveText);
        $this->abTestService->method('getTestSummary')->willReturn($summary);

        $request = Request::create('/api/ab-tests/1/live-texts', 'POST', [], [], [], [], json_encode([
            'livetext_id' => 10,
        ]));

        $response = $this->controller->addLiveText(1, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertTrue($data['success']);
        $this->assertSame($summary, $data['test']);
    }

    // =============================================
    // DELETE /api/ab-tests/{id}/live-texts/{liveTextId} (removeLiveText)
    // =============================================

    #[Test]
    public function removeLiveTextReturnsNotFoundWhenTestDoesNotExist(): void
    {
        $this->abTestRepository->method('find')->willReturn(null);

        $response = $this->controller->removeLiveText(999, 1);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('Test not found', $data['error']);
    }

    #[Test]
    public function removeLiveTextReturnsNotFoundWhenLiveTextDoesNotExist(): void
    {
        $test = $this->createAbTest(1);
        $this->abTestRepository->method('find')->willReturn($test);
        $this->liveTextRepository->method('find')->willReturn(null);

        $response = $this->controller->removeLiveText(1, 999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('LiveText not found', $data['error']);
    }

    #[Test]
    public function removeLiveTextReturnsSuccessOnSuccess(): void
    {
        $test = $this->createAbTest(1);
        $liveText = $this->createLiveText(10);
        $summary = ['id' => 1, 'name' => 'My Test', 'livetext_count' => 0];

        $this->abTestRepository->method('find')->willReturn($test);
        $this->liveTextRepository->method('find')->willReturn($liveText);
        $this->abTestService->method('getTestSummary')->willReturn($summary);

        $response = $this->controller->removeLiveText(1, 10);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertTrue($data['success']);
        $this->assertSame($summary, $data['test']);
    }

    // =============================================
    // GET /api/ab-tests/live-text/{liveTextId} (getTestsByLiveText)
    // =============================================

    #[Test]
    public function getTestsByLiveTextReturnsNotFoundWhenLiveTextDoesNotExist(): void
    {
        $this->liveTextRepository->method('find')->willReturn(null);

        $response = $this->controller->getTestsByLiveText(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('LiveText not found', $data['error']);
    }

    #[Test]
    public function getTestsByLiveTextReturnsRunningTests(): void
    {
        $liveText = $this->createLiveText(10, 'Breaking News');
        $test1 = $this->createAbTest(1, 'Test A', 'running');
        $test2 = $this->createAbTest(2, 'Test B', 'running');

        $this->liveTextRepository->method('find')->willReturn($liveText);
        $this->abTestService->method('getRunningTestsForLiveText')->willReturn([$test1, $test2]);
        $this->abTestService->method('getTestSummary')->willReturnCallback(
            fn (LiveTextAbTest $t) => ['id' => $t->getId(), 'name' => $t->getName()]
        );

        $response = $this->controller->getTestsByLiveText(10);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame(2, $data['total']);
        $this->assertCount(2, $data['tests']);
        $this->assertSame(10, $data['livetext_id']);
    }

    #[Test]
    public function getTestsByLiveTextReturnsEmptyWhenNoRunningTests(): void
    {
        $liveText = $this->createLiveText(10);

        $this->liveTextRepository->method('find')->willReturn($liveText);
        $this->abTestService->method('getRunningTestsForLiveText')->willReturn([]);

        $response = $this->controller->getTestsByLiveText(10);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame(0, $data['total']);
        $this->assertEmpty($data['tests']);
        $this->assertSame(10, $data['livetext_id']);
    }

    // =============================================
    // Edge cases
    // =============================================

    #[Test]
    public function createTestHandlesInvalidJsonBody(): void
    {
        $this->setUpControllerWithUser($this->createUser());

        $request = Request::create('/api/ab-tests', 'POST', [], [], [], [], 'not-json');

        $response = $this->controller->createTest($request);

        // json_decode returns null for invalid JSON, so isset checks fail
        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function getTestWithMultipleLiveTextsReturnsAll(): void
    {
        $test = $this->createAbTest(1, 'Multi LT Test');
        $lt1 = $this->createLiveText(10, 'LT One', 'lt-one');
        $lt2 = $this->createLiveText(20, 'LT Two', 'lt-two');
        $lt3 = $this->createLiveText(30, 'LT Three', 'lt-three');

        $test->addLiveText($lt1);
        $test->addLiveText($lt2);
        $test->addLiveText($lt3);

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService->method('getTestSummary')->willReturn(['id' => 1]);

        $response = $this->controller->getTest(1);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertCount(3, $data['live_texts']);
    }

    #[Test]
    public function completeTestHandlesEmptyBody(): void
    {
        $test = $this->createAbTest(1);
        $summary = ['id' => 1, 'status' => 'completed'];

        $this->abTestRepository->method('find')->willReturn($test);
        $this->abTestService->method('getTestSummary')->willReturn($summary);

        // Empty body -> json_decode returns null -> $data['winner_variant'] ?? null -> null
        $request = Request::create('/api/ab-tests/1/complete', 'PUT', [], [], [], [], '');

        $response = $this->controller->completeTest(1, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertTrue($data['success']);
    }

    #[Test]
    public function addLiveTextHandlesEmptyBody(): void
    {
        $test = $this->createAbTest(1);
        $this->abTestRepository->method('find')->willReturn($test);

        $request = Request::create('/api/ab-tests/1/live-texts', 'POST', [], [], [], [], '');

        $response = $this->controller->addLiveText(1, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertStringContainsString('Missing required field', $data['error']);
    }

    #[Test]
    public function createTestReturnsUnauthorizedWhenUserIsNotUserInstance(): void
    {
        // Set up with a non-User object (e.g., an anonymous token with string user)
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $paramBag = $this->createStub(ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([
            ['kernel.debug', false],
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(function (string $id): bool {
            return match ($id) {
                'parameter_bag', 'security.token_storage' => true,
                default => false,
            };
        });
        $container->method('get')->willReturnCallback(
            function (string $id) use ($paramBag, $tokenStorage) {
                return match ($id) {
                    'parameter_bag' => $paramBag,
                    'security.token_storage' => $tokenStorage,
                    default => null,
                };
            }
        );
        $this->controller->setContainer($container);

        $request = Request::create('/api/ab-tests', 'POST', [], [], [], [], json_encode([
            'name' => 'My Test',
            'variant_type' => 'template',
            'control_variant' => ['key' => 'control'],
            'test_variants' => ['variant_a' => ['key' => 'a']],
            'target_metric' => 'views',
        ]));

        $response = $this->controller->createTest($request);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $data = $this->getJsonContent($response);
        $this->assertSame('User not authenticated', $data['error']);
    }
}
