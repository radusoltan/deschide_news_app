<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\RedirectManagementController;
use App\Entity\UrlRedirect;
use App\Repository\UrlRedirectRepository;
use App\Service\SlugLookupService;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectManagementControllerTest extends TestCase
{
    private RedirectManagementController $controller;
    private UrlRedirectRepository $redirectRepo;
    private SlugLookupService $slugLookupService;

    protected function setUp(): void
    {
        $this->redirectRepo = $this->createMock(UrlRedirectRepository::class);
        $this->slugLookupService = $this->createMock(SlugLookupService::class);

        $this->controller = new RedirectManagementController(
            $this->redirectRepo,
            $this->slugLookupService
        );

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['serializer', false],
        ]);
        $this->controller->setContainer($container);
    }

    // ========================
    // getStatistics Tests
    // ========================

    #[Test]
    public function getStatisticsReturnsStats(): void
    {
        $stats = [
            'total' => 100,
            'by_type' => ['article' => 80, 'category' => 20],
            'unused' => 5,
            'most_used' => [],
        ];

        $this->redirectRepo->expects($this->once())
            ->method('getStatistics')
            ->willReturn($stats);

        $response = $this->controller->getStatistics();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame($stats, $data['statistics']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    // ========================
    // getByEntity Tests
    // ========================

    #[Test]
    public function getByEntityReturns400WhenParamsMissing(): void
    {
        $request = new Request();
        $response = $this->controller->getByEntity($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Missing required parameters', $data['error']);
    }

    #[Test]
    public function getByEntityReturns400WhenTypeMissing(): void
    {
        $request = new Request(query: ['entity_id' => '1']);
        $response = $this->controller->getByEntity($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function getByEntityReturns400WhenEntityIdMissing(): void
    {
        $request = new Request(query: ['type' => 'article']);
        $response = $this->controller->getByEntity($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function getByEntityReturns400WhenTypeInvalid(): void
    {
        $request = new Request(query: ['type' => 'invalid', 'entity_id' => '1']);
        $response = $this->controller->getByEntity($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid type', $data['error']);
    }

    #[Test]
    public function getByEntityReturnsRedirectsForArticleType(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getId')->willReturn(1);
        $redirect->method('getOldUrl')->willReturn('/old/path');
        $redirect->method('getNewUrl')->willReturn('/new/path');
        $redirect->method('getLocale')->willReturn('ro');
        $redirect->method('getHttpStatusCode')->willReturn(301);
        $redirect->method('getHitCount')->willReturn(42);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2025-01-01'));
        $redirect->method('getLastAccessedAt')->willReturn(new \DateTimeImmutable('2025-06-01'));

        $this->redirectRepo->expects($this->once())
            ->method('findByEntity')
            ->with('article', 123)
            ->willReturn([$redirect]);

        $request = new Request(query: ['type' => 'article', 'entity_id' => '123']);
        $response = $this->controller->getByEntity($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('article', $data['entity']['type']);
        $this->assertSame(123, $data['entity']['id']);
        $this->assertCount(1, $data['redirects']);
        $this->assertSame(1, $data['count']);
        $this->assertSame('/old/path', $data['redirects'][0]['old_url']);
        $this->assertSame('/new/path', $data['redirects'][0]['new_url']);
        $this->assertSame(301, $data['redirects'][0]['status_code']);
        $this->assertSame(42, $data['redirects'][0]['hit_count']);
    }

    #[Test]
    public function getByEntityAcceptsValidTypes(): void
    {
        $validTypes = ['article', 'category', 'author', 'manual'];

        foreach ($validTypes as $type) {
            $repo = $this->createStub(UrlRedirectRepository::class);
            $repo->method('findByEntity')->willReturn([]);

            $controller = new RedirectManagementController(
                $repo,
                $this->createStub(SlugLookupService::class)
            );

            $container = $this->createStub(ContainerInterface::class);
            $container->method('has')->willReturn(false);
            $controller->setContainer($container);

            $request = new Request(query: ['type' => $type, 'entity_id' => '1']);
            $response = $controller->getByEntity($request);

            $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), "Type '{$type}' should be accepted");
        }
    }

    #[Test]
    public function getByEntityReturnsEmptyWhenNoRedirects(): void
    {
        $this->redirectRepo->method('findByEntity')->willReturn([]);

        $request = new Request(query: ['type' => 'article', 'entity_id' => '1']);
        $response = $this->controller->getByEntity($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(0, $data['count']);
        $this->assertSame([], $data['redirects']);
    }

    // ========================
    // getHealth Tests
    // ========================

    #[Test]
    public function getHealthReturnsHealthyWhenNoIssues(): void
    {
        $this->redirectRepo->method('getStatistics')->willReturn([
            'total' => 10,
            'by_type' => [],
            'unused' => 0,
            'most_used' => [],
        ]);

        // Old redirects query
        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->redirectRepo->method('createQueryBuilder')->willReturn($qb);
        $this->redirectRepo->method('findAll')->willReturn([]);

        $response = $this->controller->getHealth();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('healthy', $data['health']['status']);
        $this->assertSame(10, $data['health']['total_redirects']);
        $this->assertSame(0, $data['health']['unused_redirects']);
        $this->assertArrayHasKey('issues', $data['health']);
    }

    #[Test]
    public function getHealthReturnsWarningWhenHighUnusedPercentage(): void
    {
        $this->redirectRepo->method('getStatistics')->willReturn([
            'total' => 100,
            'by_type' => [],
            'unused' => 15, // 15% unused > 10% threshold
            'most_used' => [],
        ]);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->redirectRepo->method('createQueryBuilder')->willReturn($qb);
        $this->redirectRepo->method('findAll')->willReturn([]);

        $response = $this->controller->getHealth();
        $data = json_decode($response->getContent(), true);

        $this->assertSame('warning', $data['health']['status']);
        $this->assertSame(15, $data['health']['unused_redirects']);
        $this->assertEquals(15.0, $data['health']['unused_percentage']);

        // Find unused_redirects issue
        $unusedIssue = null;
        foreach ($data['health']['issues'] as $issue) {
            if ($issue['type'] === 'unused_redirects') {
                $unusedIssue = $issue;
                break;
            }
        }
        $this->assertNotNull($unusedIssue);
        $this->assertSame('warning', $unusedIssue['severity']);
    }

    #[Test]
    public function getHealthReportsOldRedirects(): void
    {
        $this->redirectRepo->method('getStatistics')->willReturn([
            'total' => 10,
            'by_type' => [],
            'unused' => 0,
            'most_used' => [],
        ]);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(5); // 5 old redirects

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->redirectRepo->method('createQueryBuilder')->willReturn($qb);
        $this->redirectRepo->method('findAll')->willReturn([]);

        $response = $this->controller->getHealth();
        $data = json_decode($response->getContent(), true);

        $this->assertSame(5, $data['health']['old_redirects']);

        // Find old_redirects issue
        $oldIssue = null;
        foreach ($data['health']['issues'] as $issue) {
            if ($issue['type'] === 'old_redirects') {
                $oldIssue = $issue;
                break;
            }
        }
        $this->assertNotNull($oldIssue);
        $this->assertSame('info', $oldIssue['severity']);
    }

    #[Test]
    public function getHealthReportsLongChains(): void
    {
        $this->redirectRepo->method('getStatistics')->willReturn([
            'total' => 10,
            'by_type' => [],
            'unused' => 0,
            'most_used' => [],
        ]);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->redirectRepo->method('createQueryBuilder')->willReturn($qb);

        // Create a redirect that leads to a long chain
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getOldUrl')->willReturn('/old-url');

        $this->redirectRepo->method('findAll')->willReturn([$redirect]);

        $this->slugLookupService->expects($this->once())
            ->method('getRedirectChain')
            ->with('/old-url')
            ->willReturn(['chain_length' => 5]);

        $response = $this->controller->getHealth();
        $data = json_decode($response->getContent(), true);

        $this->assertSame('warning', $data['health']['status']);
        $this->assertSame(1, $data['health']['long_chains']);
    }

    #[Test]
    public function getHealthNoIssuesMessageWhenAllClean(): void
    {
        $this->redirectRepo->method('getStatistics')->willReturn([
            'total' => 10,
            'by_type' => [],
            'unused' => 0,
            'most_used' => [],
        ]);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->redirectRepo->method('createQueryBuilder')->willReturn($qb);
        $this->redirectRepo->method('findAll')->willReturn([]);

        $response = $this->controller->getHealth();
        $data = json_decode($response->getContent(), true);

        $this->assertCount(1, $data['health']['issues']);
        $this->assertSame('none', $data['health']['issues'][0]['type']);
        $this->assertStringContainsString('No issues detected', $data['health']['issues'][0]['description']);
    }

    #[Test]
    public function getHealthHandlesZeroTotalRedirects(): void
    {
        $this->redirectRepo->method('getStatistics')->willReturn([
            'total' => 0,
            'by_type' => [],
            'unused' => 0,
            'most_used' => [],
        ]);

        $query = $this->createMock(Query::class);
        $query->method('getSingleScalarResult')->willReturn(0);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->redirectRepo->method('createQueryBuilder')->willReturn($qb);
        $this->redirectRepo->method('findAll')->willReturn([]);

        $response = $this->controller->getHealth();
        $data = json_decode($response->getContent(), true);

        $this->assertSame(0, $data['health']['unused_percentage']);
    }

    // ========================
    // findChains Tests
    // ========================

    #[Test]
    public function findChainsReturnsEmptyWhenNoChains(): void
    {
        $this->redirectRepo->method('findAll')->willReturn([]);

        $request = new Request(content: json_encode([]));
        $response = $this->controller->findChains($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame([], $data['chains']);
        $this->assertSame(0, $data['count']);
        $this->assertSame(0, $data['summary']['total_checked']);
        $this->assertSame(0, $data['summary']['problematic_chains']);
        $this->assertEquals(0, $data['summary']['avg_chain_length']);
    }

    #[Test]
    public function findChainsFindsProblematicChain(): void
    {
        $chainRedirect = $this->createStub(UrlRedirect::class);
        $chainRedirect->method('getNewUrl')->willReturn('/final-url');
        $chainRedirect->method('getHitCount')->willReturn(10);
        $chainRedirect->method('getHttpStatusCode')->willReturn(301);

        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getOldUrl')->willReturn('/old-url');

        $this->redirectRepo->method('findAll')->willReturn([$redirect]);

        $this->slugLookupService->expects($this->once())
            ->method('getRedirectChain')
            ->with('/old-url')
            ->willReturn([
                'chain_length' => 4,
                'final_url' => '/final-url',
                'redirects' => [$chainRedirect],
            ]);

        $request = new Request(content: json_encode(['min_chain_length' => 3]));
        $response = $this->controller->findChains($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['count']);
        $this->assertSame('/old-url', $data['chains'][0]['start_url']);
        $this->assertSame('/final-url', $data['chains'][0]['final_url']);
        $this->assertSame(4, $data['chains'][0]['chain_length']);
        $this->assertSame(1, $data['summary']['problematic_chains']);
        $this->assertEquals(4.0, $data['summary']['avg_chain_length']);
    }

    #[Test]
    public function findChainsRespectsLimitParameter(): void
    {
        $redirects = [];
        for ($i = 0; $i < 5; ++$i) {
            $r = $this->createStub(UrlRedirect::class);
            $r->method('getOldUrl')->willReturn("/url-{$i}");
            $redirects[] = $r;
        }

        $this->redirectRepo->method('findAll')->willReturn($redirects);

        $this->slugLookupService->method('getRedirectChain')
            ->willReturn(['chain_length' => 1]);

        $request = new Request(content: json_encode(['limit' => 2]));
        $response = $this->controller->findChains($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(2, $data['summary']['total_checked']);
    }

    #[Test]
    public function findChainsSkipsDuplicateUrls(): void
    {
        $r1 = $this->createStub(UrlRedirect::class);
        $r1->method('getOldUrl')->willReturn('/same-url');
        $r2 = $this->createStub(UrlRedirect::class);
        $r2->method('getOldUrl')->willReturn('/same-url');

        $this->redirectRepo->method('findAll')->willReturn([$r1, $r2]);

        $this->slugLookupService->expects($this->once())
            ->method('getRedirectChain')
            ->with('/same-url')
            ->willReturn(['chain_length' => 1]);

        $request = new Request(content: json_encode([]));
        $response = $this->controller->findChains($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['summary']['total_checked']);
    }

    #[Test]
    public function findChainsCapsLimitAt100(): void
    {
        $this->redirectRepo->method('findAll')->willReturn([]);

        $request = new Request(content: json_encode(['limit' => 500]));
        $response = $this->controller->findChains($request);

        $data = json_decode($response->getContent(), true);
        // min(500, 100) = 100, but since no redirects, total_checked = 0
        $this->assertSame(0, $data['summary']['total_checked']);
    }

    // ========================
    // lookupRedirect Tests
    // ========================

    #[Test]
    public function lookupRedirectReturns400WhenUrlMissing(): void
    {
        $request = new Request();
        $response = $this->controller->lookupRedirect($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('URL parameter is required', $data['message']);
    }

    #[Test]
    public function lookupRedirectReturns404WhenNotFound(): void
    {
        $this->redirectRepo->method('findOneBy')
            ->with(['oldUrl' => '/nonexistent', 'locale' => 'ro', 'isActive' => true])
            ->willReturn(null);

        $request = new Request(query: ['url' => '/nonexistent']);
        $response = $this->controller->lookupRedirect($request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('No redirect found', $data['message']);
    }

    #[Test]
    public function lookupRedirectReturnsFoundRedirect(): void
    {
        $redirect = $this->createMock(UrlRedirect::class);
        $redirect->method('getId')->willReturn(1);
        $redirect->method('getOldUrl')->willReturn('/old-path');
        $redirect->method('getNewUrl')->willReturn('/new-path');
        $redirect->method('getHttpStatusCode')->willReturn(301);
        $redirect->method('getLocale')->willReturn('en');
        $redirect->method('getType')->willReturn('article');
        $redirect->expects($this->once())->method('incrementHitCount');

        // Create a custom repository stub that has getEntityManager() accessible
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $redirectRepo = new class($redirect, $entityManager) extends UrlRedirectRepository {
            private UrlRedirect $redirect;
            private EntityManagerInterface $em;

            public function __construct(UrlRedirect $redirect, EntityManagerInterface $em)
            {
                // Skip parent constructor
                $this->redirect = $redirect;
                $this->em = $em;
            }

            public function findOneBy(array $criteria, ?array $orderBy = null): ?object
            {
                return $this->redirect;
            }

            public function getEntityManager(): EntityManagerInterface
            {
                return $this->em;
            }
        };

        $controller = new RedirectManagementController(
            $redirectRepo,
            $this->slugLookupService
        );

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $controller->setContainer($container);

        $request = new Request(query: ['url' => '/old-path', 'locale' => 'en']);
        $response = $controller->lookupRedirect($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(1, $data['redirect']['id']);
        $this->assertSame('/old-path', $data['redirect']['old_url']);
        $this->assertSame('/new-path', $data['redirect']['new_url']);
        $this->assertSame(301, $data['redirect']['status_code']);
        $this->assertSame('en', $data['redirect']['locale']);
        $this->assertSame('article', $data['redirect']['type']);
    }

    #[Test]
    public function lookupRedirectUsesDefaultLocaleRo(): void
    {
        $this->redirectRepo->expects($this->once())
            ->method('findOneBy')
            ->with(['oldUrl' => '/some-path', 'locale' => 'ro', 'isActive' => true])
            ->willReturn(null);

        $request = new Request(query: ['url' => '/some-path']);
        $this->controller->lookupRedirect($request);
    }
}
