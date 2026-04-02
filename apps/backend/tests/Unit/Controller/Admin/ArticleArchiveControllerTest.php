<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Admin;

use App\Controller\Admin\ArticleArchiveController;
use App\Entity\Article;
use App\Enum\ArchiveReason;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Service\ArticleArchiveService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class ArticleArchiveControllerTest extends TestCase
{
    private ArticleArchiveController $controller;
    private ArticleArchiveService $archiveService;
    private ArticleRepository $articleRepo;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->archiveService = $this->createMock(ArticleArchiveService::class);
        $this->articleRepo = $this->createStub(ArticleRepository::class);
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->controller = new ArticleArchiveController(
            $this->archiveService,
            $this->articleRepo,
            $this->em,
            $this->logger
        );

        $this->setUpContainer();
    }

    private function setUpContainer(): void
    {
        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('admin@test.com');

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['serializer', false],
            ['security.token_storage', true],
        ]);
        $container->method('get')->willReturnMap([
            ['security.token_storage', $tokenStorage],
        ]);
        $this->controller->setContainer($container);
    }

    // ========================
    // archiveArticle Tests
    // ========================

    #[Test]
    public function archiveArticleReturns404WhenArticleNotFound(): void
    {
        $this->articleRepo->method('find')->with(999)->willReturn(null);

        $request = new Request(content: json_encode(['reason' => 'old_content']));
        $response = $this->controller->archiveArticle(999, $request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('Article not found', $data['error']);
    }

    #[Test]
    public function archiveArticleReturns400WhenReasonMissing(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);

        $this->articleRepo->method('find')->with(1)->willReturn($article);

        $request = new Request(content: json_encode([]));
        $response = $this->controller->archiveArticle(1, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Missing required field: reason', $data['error']);
    }

    #[Test]
    public function archiveArticleReturns400WhenReasonInvalid(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);

        $this->articleRepo->method('find')->with(1)->willReturn($article);

        $request = new Request(content: json_encode(['reason' => 'invalid_reason']));
        $response = $this->controller->archiveArticle(1, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid archive reason', $data['error']);
        $this->assertStringContainsString('old_content', $data['error']);
    }

    #[Test]
    public function archiveArticleReturns400WhenAlreadyArchived(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getStatus')->willReturn(ArticleStatus::ARCHIVED);

        $this->articleRepo->method('find')->with(1)->willReturn($article);

        $request = new Request(content: json_encode(['reason' => 'old_content']));
        $response = $this->controller->archiveArticle(1, $request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('Article is already archived', $data['error']);
    }

    #[Test]
    public function archiveArticleReturnsSuccessOnValidArchive(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Test Article');
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $article->method('getArchivedAt')->willReturn(new DateTimeImmutable('2025-06-01'));
        $article->method('getArchiveReason')->willReturn(ArchiveReason::OLD_CONTENT);

        $this->articleRepo->method('find')->with(1)->willReturn($article);

        $this->archiveService->expects($this->once())
            ->method('archiveArticle')
            ->with($article, ArchiveReason::OLD_CONTENT);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Article archived by admin', $this->isType('array'));

        $request = new Request(content: json_encode(['reason' => 'old_content']));
        $response = $this->controller->archiveArticle(1, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Article archived successfully', $data['message']);
        $this->assertSame(1, $data['article']['id']);
        $this->assertSame('Test Article', $data['article']['title']);
    }

    #[Test]
    public function archiveArticleReturns500OnException(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);

        $this->articleRepo->method('find')->with(1)->willReturn($article);

        $this->archiveService->method('archiveArticle')
            ->willThrowException(new \Exception('DB error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to archive article', $this->isType('array'));

        $request = new Request(content: json_encode(['reason' => 'old_content']));
        $response = $this->controller->archiveArticle(1, $request);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Failed to archive article', $data['error']);
    }

    #[Test]
    public function archiveArticleAcceptsAllValidReasons(): void
    {
        $validReasons = ['old_content', 'outdated_info', 'legal_request', 'duplicate', 'low_quality', 'policy_violation', 'manual'];

        foreach ($validReasons as $reason) {
            $article = $this->createStub(Article::class);
            $article->method('getId')->willReturn(1);
            $article->method('getTitle')->willReturn('Test');
            $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
            $article->method('getArchivedAt')->willReturn(null);
            $article->method('getArchiveReason')->willReturn(null);

            $articleRepo = $this->createStub(ArticleRepository::class);
            $articleRepo->method('find')->willReturn($article);

            $archiveService = $this->createStub(ArticleArchiveService::class);
            $logger = $this->createStub(LoggerInterface::class);

            $controller = new ArticleArchiveController(
                $archiveService,
                $articleRepo,
                $this->em,
                $logger
            );

            $user = $this->createStub(UserInterface::class);
            $user->method('getUserIdentifier')->willReturn('admin@test.com');

            $token = $this->createStub(TokenInterface::class);
            $token->method('getUser')->willReturn($user);

            $tokenStorage = $this->createStub(TokenStorageInterface::class);
            $tokenStorage->method('getToken')->willReturn($token);

            $container = $this->createStub(ContainerInterface::class);
            $container->method('has')->willReturnMap([
                ['serializer', false],
                ['security.token_storage', true],
            ]);
            $container->method('get')->willReturnMap([
                ['security.token_storage', $tokenStorage],
            ]);
            $controller->setContainer($container);

            $request = new Request(content: json_encode(['reason' => $reason]));
            $response = $controller->archiveArticle(1, $request);

            $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), "Reason '{$reason}' should be accepted");
        }
    }

    // ========================
    // unarchiveArticle Tests
    // ========================

    #[Test]
    public function unarchiveArticleReturns404WhenNotFound(): void
    {
        $this->articleRepo->method('find')->with(999)->willReturn(null);

        $response = $this->controller->unarchiveArticle(999);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('Article not found', $data['error']);
    }

    #[Test]
    public function unarchiveArticleReturns400WhenNotArchived(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);

        $this->articleRepo->method('find')->with(1)->willReturn($article);

        $response = $this->controller->unarchiveArticle(1);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('Article is not archived', $data['error']);
    }

    #[Test]
    public function unarchiveArticleReturnsSuccessOnValid(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Archived Article');
        $article->method('getStatus')->willReturn(ArticleStatus::ARCHIVED);
        $article->method('getArchivedAt')->willReturn(null);
        $article->method('getArchiveReason')->willReturn(null);

        $this->articleRepo->method('find')->with(1)->willReturn($article);

        $this->archiveService->expects($this->once())
            ->method('unarchiveArticle')
            ->with($article);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Article unarchived by admin', $this->isType('array'));

        $response = $this->controller->unarchiveArticle(1);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Article unarchived successfully', $data['message']);
        $this->assertSame(1, $data['article']['id']);
    }

    #[Test]
    public function unarchiveArticleReturns500OnException(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getStatus')->willReturn(ArticleStatus::ARCHIVED);

        $this->articleRepo->method('find')->with(1)->willReturn($article);

        $this->archiveService->method('unarchiveArticle')
            ->willThrowException(new \Exception('DB error'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to unarchive article', $this->isType('array'));

        $response = $this->controller->unarchiveArticle(1);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Failed to unarchive article', $data['error']);
    }

    // ========================
    // bulkArchiveOldArticles Tests
    // ========================

    #[Test]
    public function bulkArchiveReturns400WhenYearsOldLessThanOne(): void
    {
        $request = new Request(content: json_encode(['years_old' => 0]));
        $response = $this->controller->bulkArchiveOldArticles($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('years_old must be positive', $data['error']);
    }

    #[Test]
    public function bulkArchiveReturns400WhenBatchSizeTooLarge(): void
    {
        $request = new Request(content: json_encode(['years_old' => 4, 'batch_size' => 1001]));
        $response = $this->controller->bulkArchiveOldArticles($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('batch_size must be between 1 and 1000', $data['error']);
    }

    #[Test]
    public function bulkArchiveReturns400WhenBatchSizeZero(): void
    {
        $request = new Request(content: json_encode(['years_old' => 4, 'batch_size' => 0]));
        $response = $this->controller->bulkArchiveOldArticles($request);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    #[Test]
    public function bulkArchiveReturnsSuccessWithDefaultParams(): void
    {
        $cutoffDate = new DateTimeImmutable('-4 years');
        $this->archiveService->expects($this->once())
            ->method('bulkArchiveOldArticles')
            ->with(4, 100)
            ->willReturn([
                'total_archived' => 50,
                'cutoff_date' => $cutoffDate,
            ]);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Bulk archive operation completed by admin', $this->isType('array'));

        $request = new Request(content: json_encode([]));
        $response = $this->controller->bulkArchiveOldArticles($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Bulk archive completed', $data['message']);
        $this->assertSame(50, $data['results']['total_archived']);
        $this->assertSame(100, $data['results']['batch_size']);
        $this->assertSame(4, $data['results']['years_old']);
    }

    #[Test]
    public function bulkArchiveReturnsSuccessWithCustomParams(): void
    {
        $cutoffDate = new DateTimeImmutable('-2 years');
        $this->archiveService->expects($this->once())
            ->method('bulkArchiveOldArticles')
            ->with(2, 50)
            ->willReturn([
                'total_archived' => 20,
                'cutoff_date' => $cutoffDate,
            ]);

        $request = new Request(content: json_encode(['years_old' => 2, 'batch_size' => 50]));
        $response = $this->controller->bulkArchiveOldArticles($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(20, $data['results']['total_archived']);
        $this->assertSame(50, $data['results']['batch_size']);
        $this->assertSame(2, $data['results']['years_old']);
    }

    #[Test]
    public function bulkArchiveReturns500OnException(): void
    {
        $this->archiveService->method('bulkArchiveOldArticles')
            ->willThrowException(new \Exception('Timeout'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Bulk archive operation failed', $this->isType('array'));

        $request = new Request(content: json_encode(['years_old' => 4]));
        $response = $this->controller->bulkArchiveOldArticles($request);

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Bulk archive operation failed', $data['error']);
    }

    // ========================
    // getArchiveStatistics Tests
    // ========================

    #[Test]
    public function getArchiveStatisticsReturnsSuccess(): void
    {
        $stats = [
            'total_articles' => 1000,
            'archived_articles' => 200,
            'archive_percentage' => 20.0,
        ];

        $this->archiveService->expects($this->once())
            ->method('getArchiveStatistics')
            ->willReturn($stats);

        $response = $this->controller->getArchiveStatistics();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals($stats, $data['statistics']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    #[Test]
    public function getArchiveStatisticsReturns500OnException(): void
    {
        $this->archiveService->method('getArchiveStatistics')
            ->willThrowException(new \Exception('Query failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to retrieve archive statistics', $this->isType('array'));

        $response = $this->controller->getArchiveStatistics();

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Failed to retrieve statistics', $data['error']);
    }
}
