<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Editorial;

use App\Command\Editorial\ContextExportCommand;
use App\Entity\Article;
use App\Entity\Topic;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Service\Editorial\ArticleContextService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ContextExportCommandTest extends TestCase
{
    private ArticleContextService $contextService;
    private ArticleRepository $articleRepository;
    private EntityManagerInterface $em;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->contextService = $this->createMock(ArticleContextService::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $command = new ContextExportCommand(
            $this->contextService,
            $this->articleRepository,
            $this->em,
        );

        $this->commandTester = new CommandTester($command);
    }

    public function testExportArticleMarkdown_outputsToStdout(): void
    {
        $article = $this->createArticleMock(1);
        $this->articleRepository->method('find')->with(1)->willReturn($article);

        $this->contextService->method('buildMarkdownForArticle')
            ->with($article, 'ro')
            ->willReturn("---\ntitle: Test\n---\n\n# Test Article\n\nContent here.\n");

        $this->commandTester->execute([
            'type' => 'article',
            'id' => '1',
            '--format' => 'markdown',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('---', $output);
        $this->assertStringContainsString('# Test Article', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testExportArticleJson_validStructure(): void
    {
        $article = $this->createArticleMock(2);
        $this->articleRepository->method('find')->with(2)->willReturn($article);

        $this->contextService->method('buildJsonForArticle')
            ->with($article, 'ro')
            ->willReturn([
                'id' => 'art-2025-01-15-test',
                'article_id' => 2,
                'title' => ['ro' => 'Test', 'en' => null, 'ru' => null],
                'type' => 'news',
                'language' => 'ro',
            ]);

        $this->commandTester->execute([
            'type' => 'article',
            'id' => '2',
            '--format' => 'json',
        ]);

        $output = $this->commandTester->getDisplay();
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('article_id', $decoded);
        $this->assertSame(2, $decoded['article_id']);
        $this->assertArrayHasKey('title', $decoded);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testExportArticle_notFound_returnsInvalid(): void
    {
        $this->articleRepository->method('find')->willReturn(null);

        $this->commandTester->execute([
            'type' => 'article',
            'id' => '99999',
        ]);

        // exportArticle returns null when not found → match returns null → INVALID
        $this->assertSame(Command::INVALID, $this->commandTester->getStatusCode());
    }

    public function testExportArticle_withLocaleOption(): void
    {
        $article = $this->createArticleMock(3);
        $this->articleRepository->method('find')->with(3)->willReturn($article);

        $this->contextService->expects($this->once())
            ->method('buildMarkdownForArticle')
            ->with($article, 'en')
            ->willReturn("---\nlanguage: en\n---\n\n# EN Article\n");

        $this->commandTester->execute([
            'type' => 'article',
            'id' => '3',
            '--locale' => 'en',
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testExportArticle_withOutputFlag_writesFile(): void
    {
        $article = $this->createArticleMock(4);
        $this->articleRepository->method('find')->with(4)->willReturn($article);

        $this->contextService->method('buildMarkdownForArticle')
            ->willReturn("---\ntitle: File Test\n---\n\n# File Test\n");

        $tmpFile = tempnam(sys_get_temp_dir(), 'context_test_');

        try {
            $this->commandTester->execute([
                'type' => 'article',
                'id' => '4',
                '--output' => $tmpFile,
            ]);

            $this->assertSame(0, $this->commandTester->getStatusCode());
            $this->assertFileExists($tmpFile);
            $content = file_get_contents($tmpFile);
            $this->assertStringContainsString('# File Test', $content);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testExportTopics_json(): void
    {
        $topic = $this->createMock(Topic::class);
        $topic->method('getId')->willReturn(1);
        $topic->method('getTitle')->willReturn('Integrare UE');
        $topic->method('getSlug')->willReturn('integrare-ue');
        $topic->method('getDescription')->willReturn('Procesul de integrare');

        $topicRepo = $this->createMock(EntityRepository::class);
        $topicRepo->method('findBy')->willReturn([$topic]);

        $this->em->method('getRepository')
            ->with(Topic::class)
            ->willReturn($topicRepo);

        $this->commandTester->execute([
            'type' => 'topics',
            '--format' => 'json',
        ]);

        $output = $this->commandTester->getDisplay();
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertSame('integrare-ue', $decoded[0]['slug']);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testExportTopics_markdown(): void
    {
        $topic = $this->createMock(Topic::class);
        $topic->method('getId')->willReturn(1);
        $topic->method('getTitle')->willReturn('Economie');
        $topic->method('getSlug')->willReturn('economie');
        $topic->method('getDescription')->willReturn(null);

        $topicRepo = $this->createMock(EntityRepository::class);
        $topicRepo->method('findBy')->willReturn([$topic]);

        $this->em->method('getRepository')
            ->with(Topic::class)
            ->willReturn($topicRepo);

        $this->commandTester->execute([
            'type' => 'topics',
            '--format' => 'markdown',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('# Topics', $output);
        $this->assertStringContainsString('**Economie**', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testInvalidFormat_returnsInvalid(): void
    {
        $this->commandTester->execute([
            'type' => 'article',
            'id' => '1',
            '--format' => 'xml',
        ]);

        $this->assertSame(2, $this->commandTester->getStatusCode());
    }

    public function testInvalidType_returnsInvalid(): void
    {
        $this->commandTester->execute([
            'type' => 'unknown',
        ]);

        $this->assertSame(2, $this->commandTester->getStatusCode());
    }

    private function createArticleMock(int $id): Article
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn($id);
        $article->method('getTitle')->willReturn('Test Article');
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $article->method('getTags')->willReturn(new ArrayCollection());
        $article->method('getTopics')->willReturn(new ArrayCollection());

        return $article;
    }
}
