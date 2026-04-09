<?php

declare(strict_types=1);

namespace App\Tests\Service\Translation;

use App\Dto\Translation\TranslationEvaluationResult;
use App\Dto\Translation\TranslationOptimizationResult;
use App\Entity\Article;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Translation\GeminiStructuredTranslator;
use App\Service\Translation\TranslationEvaluatorService;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TranslationEvaluatorServiceTest extends TestCase
{
    #[Test]
    public function evaluationResultHighScoreNoRevision(): void
    {
        $result = new TranslationEvaluationResult(
            score: 0.92,
            issues: [],
            needsRevision: false,
            revisionInstructions: null,
        );

        $this->assertFalse($result->needsRevision);
        $this->assertGreaterThanOrEqual(0.85, $result->score);
        $this->assertEmpty($result->issues);
    }

    #[Test]
    public function evaluationResultLowScoreNeedsRevision(): void
    {
        $result = new TranslationEvaluationResult(
            score: 0.62,
            issues: [
                ['type' => 'accuracy', 'severity' => 'major', 'description' => 'Wrong date'],
                ['type' => 'diacritics', 'severity' => 'critical', 'description' => 'Cedilla instead of comma-below'],
            ],
            needsRevision: true,
            revisionInstructions: 'Fix dates and diacritics',
        );

        $this->assertTrue($result->needsRevision);
        $this->assertLessThan(0.85, $result->score);
        $this->assertCount(2, $result->issues);
        $this->assertNotNull($result->revisionInstructions);
    }

    #[Test]
    public function optimizationResultTracksIterations(): void
    {
        $result = new TranslationOptimizationResult(
            articleId: 42,
            targetLang: 'en',
            initialScore: 0.65,
            finalScore: 0.88,
            iterations: 2,
            finalStatus: 'complete',
        );

        $this->assertSame(42, $result->articleId);
        $this->assertSame('en', $result->targetLang);
        $this->assertSame(2, $result->iterations);
        $this->assertSame('complete', $result->finalStatus);
        $this->assertGreaterThan($result->initialScore, $result->finalScore);
    }

    #[Test]
    public function optimizationResultMaxIterationsRespected(): void
    {
        $result = new TranslationOptimizationResult(
            articleId: 100,
            targetLang: 'ru',
            initialScore: 0.50,
            finalScore: 0.72,
            iterations: 2,
            finalStatus: 'needs_review',
        );

        $this->assertSame('needs_review', $result->finalStatus);
        $this->assertLessThanOrEqual(2, $result->iterations);
        $this->assertLessThan(0.85, $result->finalScore);
    }

    #[Test]
    public function evaluateAndOptimizeThrowsForMissingArticle(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn(null);

        // GeminiStructuredTranslator is final, create a real instance with a mocked GeminiCliService
        $geminiCli = new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger());
        $translator = new GeminiStructuredTranslator($geminiCli, new NullLogger());

        $service = new TranslationEvaluatorService($em, $translator, $geminiCli, new NullLogger());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Article #999 not found');

        $service->evaluateAndOptimize(999, 'en');
    }

    #[Test]
    public function evaluateAndOptimizeReturnsNeedsReviewForEmptyContent(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getContent')->willReturn('');
        $article->method('getTitle')->willReturn('Test');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($article);

        $geminiCli = new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger());
        $translator = new GeminiStructuredTranslator($geminiCli, new NullLogger());
        $service = new TranslationEvaluatorService($em, $translator, $geminiCli, new NullLogger());

        $result = $service->evaluateAndOptimize(1, 'en');

        $this->assertSame('needs_review', $result->finalStatus);
        $this->assertSame(0.0, $result->initialScore);
        $this->assertSame(0, $result->iterations);
    }

    #[Test]
    public function evaluateAndOptimizeReturnsNeedsReviewForMissingTranslation(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getContent')->willReturn('<p>Conținut original</p>');
        $article->method('getTitle')->willReturn('Titlu');
        $article->method('getId')->willReturn(1);

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->method('findTranslations')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('find')->willReturn($article);
        $em->method('getRepository')->willReturn($translationRepo);

        $geminiCli = new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger());
        $translator = new GeminiStructuredTranslator($geminiCli, new NullLogger());
        $service = new TranslationEvaluatorService($em, $translator, $geminiCli, new NullLogger());

        $result = $service->evaluateAndOptimize(1, 'en');

        $this->assertSame('needs_review', $result->finalStatus);
        $this->assertSame(0.0, $result->initialScore);
    }
}
