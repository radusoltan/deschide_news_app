<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\NotebookLM;

use App\Dto\NotebookLM\FactCheckResult;
use App\Entity\Article;
use App\Entity\Topic;
use App\Service\NotebookLM\NotebookLmFactCheckService;
use App\Service\NotebookLM\NotebookLMService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;

class NotebookLmFactCheckServiceTest extends TestCase
{
    private function createArticle(string $title = 'Test Article', string $content = 'Test content'): Article
    {
        $article = new Article();
        $article->setTitle($title);
        $article->setContent($content);

        return $article;
    }

    private function createTopic(?string $notebookId = 'nb-test'): Topic
    {
        $topic = new Topic();
        $topic->setTitle('Politica');
        if ($notebookId !== null) {
            $topic->setNotebookLmId($notebookId);
        }

        return $topic;
    }

    public function testFactCheckReturnsNullWhenTopicHasNoNotebook(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('resolveNotebookId')->willReturn(null);

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        $result = $service->factCheck($this->createArticle(), $this->createTopic(null));

        self::assertNull($result);
    }

    public function testFactCheckReturnsResultOnSuccess(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('resolveNotebookId')->willReturn('nb-test');
        $notebookLM->method('ask')->willReturn('No contradictions found in sources.');

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        $result = $service->factCheck($this->createArticle(), $this->createTopic());

        self::assertInstanceOf(FactCheckResult::class, $result);
        self::assertSame('No contradictions found in sources.', $result->answer);
        self::assertFalse($result->cached);
    }

    public function testFactCheckUsesCustomQuestion(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('resolveNotebookId')->willReturn('nb-test');
        $notebookLM->expects(self::once())
            ->method('ask')
            ->with('nb-test', 'Is X true?')
            ->willReturn('Yes, confirmed by 3 sources.');

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        $result = $service->factCheck($this->createArticle(), $this->createTopic(), 'Is X true?');

        self::assertSame('Yes, confirmed by 3 sources.', $result->answer);
    }

    public function testFactCheckReturnsNullWhenNotebookLmFails(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('resolveNotebookId')->willReturn('nb-test');
        $notebookLM->method('ask')->willReturn(null);

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        $result = $service->factCheck($this->createArticle(), $this->createTopic());

        self::assertNull($result);
    }

    public function testIsAvailableForTopicReturnsFalseWithoutNotebook(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('isAvailable')->willReturn(true);

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        self::assertFalse($service->isAvailableForTopic($this->createTopic(null)));
    }

    public function testIsAvailableForTopicReturnsFalseWhenServiceDisabled(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('isAvailable')->willReturn(false);

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        self::assertFalse($service->isAvailableForTopic($this->createTopic()));
    }

    public function testIsAvailableForTopicReturnsTrueWhenReady(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('isAvailable')->willReturn(true);

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        self::assertTrue($service->isAvailableForTopic($this->createTopic()));
    }

    public function testFactCheckResultToArray(): void
    {
        $result = new FactCheckResult(
            answer: 'Test answer',
            question: 'Test question',
            topicId: 42,
            notebookId: 'nb-42',
            cached: false,
            checkedAt: new \DateTimeImmutable('2026-04-16T12:00:00+00:00'),
        );

        $array = $result->toArray();

        self::assertSame('Test answer', $array['answer']);
        self::assertSame('Test question', $array['question']);
        self::assertSame(42, $array['topicId']);
        self::assertSame('nb-42', $array['notebookId']);
        self::assertFalse($array['cached']);
    }
}
