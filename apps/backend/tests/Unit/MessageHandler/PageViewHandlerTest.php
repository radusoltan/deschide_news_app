<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Article;
use App\Entity\Category;
use App\Message\PageViewEvent;
use App\MessageHandler\PageViewHandler;
use App\Repository\ArticleRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class PageViewHandlerTest extends TestCase
{
    private EntityManagerInterface $em;
    private ArticleRepository $articleRepository;
    private CategoryRepository $categoryRepository;
    private LoggerInterface $logger;
    private PageViewHandler $handler;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->articleRepository = $this->createStub(ArticleRepository::class);
        $this->categoryRepository = $this->createStub(CategoryRepository::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->handler = new PageViewHandler(
            $this->em,
            $this->articleRepository,
            $this->categoryRepository,
            $this->logger
        );
    }

    public function testInvokePersistsPageView(): void
    {
        $article = $this->createStub(Article::class);
        $category = $this->createStub(Category::class);
        $article->method('getCategory')->willReturn($category);

        $this->articleRepository->method('find')->with(100)->willReturn($article);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $message = new PageViewEvent(
            articleId: 100,
            visitorId: 'v-123',
            ipAddress: '10.0.0.1',
            userAgent: 'Mozilla/5.0',
            referrer: 'https://deschide.md',
            timestamp: new \DateTimeImmutable(),
        );

        ($this->handler)($message);
    }

    public function testInvokeAutoDetectsCategoryFromArticle(): void
    {
        $category = $this->createStub(Category::class);
        $article = $this->createStub(Article::class);
        $article->method('getCategory')->willReturn($category);

        $this->articleRepository->method('find')->with(200)->willReturn($article);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $message = new PageViewEvent(
            articleId: 200,
            visitorId: 'v-456',
            ipAddress: null,
            userAgent: null,
            referrer: null,
            timestamp: new \DateTimeImmutable(),
            categoryId: null, // Should auto-detect from article
        );

        ($this->handler)($message);
    }

    public function testInvokeWithExplicitCategoryId(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getCategory')->willReturn(null);
        $category = $this->createStub(Category::class);

        $this->articleRepository->method('find')->with(300)->willReturn($article);
        $this->categoryRepository->method('find')->with(5)->willReturn($category);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $message = new PageViewEvent(
            articleId: 300,
            visitorId: 'v-789',
            ipAddress: '1.2.3.4',
            userAgent: null,
            referrer: null,
            timestamp: new \DateTimeImmutable(),
            categoryId: 5,
        );

        ($this->handler)($message);
    }

    public function testInvokeRethrowsException(): void
    {
        $this->articleRepository->method('find')->willReturn(null);
        $this->em->method('persist')->willThrowException(new \RuntimeException('DB error'));

        $this->expectException(\RuntimeException::class);

        $message = new PageViewEvent(
            articleId: 1,
            visitorId: 'v-err',
            ipAddress: null,
            userAgent: null,
            referrer: null,
            timestamp: new \DateTimeImmutable(),
        );

        ($this->handler)($message);
    }
}
