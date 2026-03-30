<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Article;
use App\Entity\Category;
use App\Message\TranslateArticleMessage;
use App\MessageHandler\TranslateArticleHandler;
use App\Repository\ArticleRepository;
use App\Service\NotificationService;
use App\Service\ProcessResult;
use App\Service\TranslationResultProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(TranslateArticleHandler::class)]
class TranslateArticleHandlerTest extends TestCase
{
    private ArticleRepository $articleRepository;
    private TranslationResultProcessor $resultProcessor;
    private EntityManagerInterface $em;
    private TranslateArticleHandler $handler;

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        // TranslationResultProcessor is final — build a real one with mocked deps
        $this->resultProcessor = new TranslationResultProcessor(
            $this->em,
            $this->createStub(NotificationService::class),
            new NullLogger(),
        );

        $this->handler = new TranslateArticleHandler(
            $this->articleRepository,
            $this->resultProcessor,
            $this->em,
            new NullLogger(),
            '/usr/bin/gemini', // not actually called — process is not spawned
            '/tmp',
        );
    }

    public function testArticleNotFoundReturnsEarly(): void
    {
        $this->articleRepository->method('find')->willReturn(null);
        // No flush expected — handler returns before any work
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateArticleMessage(999));
    }

    public function testEmptyContentReturnsEarly(): void
    {
        $article = $this->createArticleStub(id: 1, title: '', content: '');
        $this->articleRepository->method('find')->willReturn($article);
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateArticleMessage(1));
    }

    public function testEmptyTitleReturnsEarly(): void
    {
        $article = $this->createArticleStub(id: 1, title: '', content: '<p>content</p>');
        $this->articleRepository->method('find')->willReturn($article);
        $this->em->expects($this->never())->method('flush');

        ($this->handler)(new TranslateArticleMessage(1));
    }

    private function createArticleStub(int $id, string $title, string $content): Article
    {
        $category = $this->createStub(Category::class);
        $category->method('getTitle')->willReturn('Politica');

        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn($id);
        $article->method('getTitle')->willReturn($title);
        $article->method('getContent')->willReturn($content);
        $article->method('getLead')->willReturn('Lead text');
        $article->method('getCategory')->willReturn($category);
        $article->method('getAuthors')->willReturn(new ArrayCollection());

        return $article;
    }
}
