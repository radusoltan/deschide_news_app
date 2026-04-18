<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\NotebookLM;

use App\Dto\NotebookLM\FactCheckResult;
use App\Entity\Article;
use App\Entity\Topic;
use App\Service\NotebookLM\NotebookLmFactCheckService;
use App\Service\NotebookLM\NotebookLMService;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertFalse($array['contradictory'], 'Default answer with no markers is not contradictory');
    }

    // ===================================================================
    // Sprint 55 T55.10 — factCheckClaim + isContradictory
    // ===================================================================

    public function testFactCheckClaimReturnsNullWhenTopicHasNoNotebook(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('resolveNotebookId')->willReturn(null);

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        self::assertNull($service->factCheckClaim('some claim', $this->createTopic(null)));
    }

    public function testFactCheckClaimReturnsResultOnSuccess(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('resolveNotebookId')->willReturn('nb-test');
        $notebookLM->method('ask')->willReturn('Afirmația este susținută de surse oficiale.');

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        $result = $service->factCheckClaim(
            'Un oficial a anunțat deschiderea frontului NATO.',
            $this->createTopic(),
        );

        self::assertInstanceOf(FactCheckResult::class, $result);
        self::assertStringContainsString('susținută', $result->answer);
        self::assertFalse($result->isContradictory());
    }

    public function testFactCheckClaimReturnsNullWhenClaimTextEmpty(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('resolveNotebookId')->willReturn('nb-test');
        $notebookLM->expects(self::never())->method('ask');

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        self::assertNull($service->factCheckClaim('   ', $this->createTopic()));
        self::assertNull($service->factCheckClaim('', $this->createTopic()));
    }

    public function testFactCheckClaimUsesCustomQuestion(): void
    {
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('resolveNotebookId')->willReturn('nb-test');
        $notebookLM->expects(self::once())
            ->method('ask')
            ->with('nb-test', 'Este susținut afirmația că X s-a întâmplat?')
            ->willReturn('Da, confirmat.');

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        $result = $service->factCheckClaim(
            'X s-a întâmplat',
            $this->createTopic(),
            'Este susținut afirmația că X s-a întâmplat?',
        );

        self::assertSame('Da, confirmat.', $result->answer);
    }

    public function testFactCheckClaimAndFactCheckUseDistinctCacheKeys(): void
    {
        // Same topic, same "question-like" input → different cache keys so
        // one call doesn't serve the other's cached answer.
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('resolveNotebookId')->willReturn('nb-test');
        $notebookLM->expects(self::exactly(2))
            ->method('ask')
            ->willReturnOnConsecutiveCalls('first answer', 'second answer');

        $cache = new ArrayAdapter();
        $service = new NotebookLmFactCheckService($notebookLM, $cache, new NullLogger());

        $r1 = $service->factCheck($this->createArticle('Shared topic'), $this->createTopic(), 'Q');
        $r2 = $service->factCheckClaim('Shared topic', $this->createTopic(), 'Q');

        self::assertNotNull($r1);
        self::assertNotNull($r2);
        self::assertSame('first answer', $r1->answer);
        self::assertSame('second answer', $r2->answer);
    }

    public function testFactCheckDelegatesToFactCheckClaim(): void
    {
        // factCheck() internally calls factCheckClaim() with a composed
        // title+lead. Validate the delegation by asserting the question
        // reaches the CLI with the expected shape.
        $notebookLM = $this->createMock(NotebookLMService::class);
        $notebookLM->method('resolveNotebookId')->willReturn('nb-test');

        $capturedQuestion = null;
        $notebookLM->method('ask')
            ->willReturnCallback(function (string $notebookId, string $q) use (&$capturedQuestion): string {
                $capturedQuestion = $q;

                return 'ok';
            });

        $service = new NotebookLmFactCheckService(
            $notebookLM,
            new ArrayAdapter(),
            new NullLogger(),
        );

        $article = new Article();
        $article->setTitle('Claim title');
        $article->setLead('Short lead text.');

        $service->factCheck($article, $this->createTopic());

        self::assertNotNull($capturedQuestion);
        self::assertStringContainsString('Claim title', $capturedQuestion);
    }

    // ===================================================================
    // FactCheckResult::isContradictory heuristic
    // ===================================================================

    #[DataProvider('contradictionAnswers')]
    public function testIsContradictoryMatchesRomanianMarkers(string $answer, bool $expected): void
    {
        $result = new FactCheckResult(
            answer: $answer,
            question: 'q',
            topicId: 1,
            notebookId: 'nb',
            cached: false,
            checkedAt: new \DateTimeImmutable(),
        );

        self::assertSame($expected, $result->isContradictory(), sprintf('answer=%s', mb_substr($answer, 0, 50)));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function contradictionAnswers(): iterable
    {
        yield 'clean affirmation' => ['Afirmația este susținută de sursele citate.', false];
        yield 'neutral recap' => ['Sursele descriu aceeași secvență de evenimente.', false];
        yield 'explicit fals' => ['Această informație este falsă conform surselor.', true];
        yield 'contrazice marker' => ['Surse oficiale contrazic această afirmație.', true];
        yield 'nu este adevărat' => ['Nu este adevărat ce se afirmă în titlu.', true];
        yield 'dezmintit' => ['Ministerul a dezmințit public aceste informații.', true];
        yield 'nu este confirmat' => ['Afirmația nu este confirmată de surse independente.', true];
        yield 'case-insensitive match' => ['NU ESTE ADEVĂRAT conform raportului.', true];
        yield 'partial overlap not a marker' => ['Este foarte probabil adevărat.', false];
    }
}
