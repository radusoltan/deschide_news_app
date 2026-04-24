<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Entity\Article;
use App\Entity\PressRelease;
use App\Entity\Source;
use App\Repository\AppSettingRepository;
use App\Service\Editorial\AutoPublishGateService;
use App\Service\Editorial\SensitiveTopicDetector;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AutoPublishGateServiceTest extends TestCase
{
    private AutoPublishGateService $gate;
    private AppSettingRepository $settings;
    private SensitiveTopicDetector $sensitiveDetector;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->sensitiveDetector = $this->createMock(SensitiveTopicDetector::class);

        $this->gate = new AutoPublishGateService(
            $this->sensitiveDetector,
            $this->settings,
            new NullLogger(),
        );
    }

    public function testAutoPublishDisabledBlocksEverything(): void
    {
        $this->settings->method('getBool')->willReturn(false);

        $article = $this->createAiArticle(0.95, 5, 500);

        $decision = $this->gate->evaluate($article);

        $this->assertFalse($decision->canPublish);
        $this->assertSame('pending_review', $decision->gate);
        $this->assertContains('auto_publish_disabled', $decision->reasons);
    }

    public function testAiArticleHighConfidenceAutoPublishes(): void
    {
        $this->configureEnabled();
        $this->sensitiveDetector->method('isSensitive')->willReturn(false);

        $article = $this->createAiArticle(0.90, 5, 500);

        $decision = $this->gate->evaluate($article);

        $this->assertTrue($decision->canPublish);
        $this->assertSame('auto_publish', $decision->gate);
    }

    public function testAiArticleLowConfidenceBlocked(): void
    {
        $this->configureEnabled();
        $this->sensitiveDetector->method('isSensitive')->willReturn(false);

        $article = $this->createAiArticle(0.70, 5, 500);

        $decision = $this->gate->evaluate($article);

        $this->assertFalse($decision->canPublish);
        $this->assertStringContainsString('low_confidence', $decision->reasons[0]);
    }

    public function testAiArticleFewSourcesBlocked(): void
    {
        $this->configureEnabled();
        $this->sensitiveDetector->method('isSensitive')->willReturn(false);

        $article = $this->createAiArticle(0.90, 2, 500);

        $decision = $this->gate->evaluate($article);

        $this->assertFalse($decision->canPublish);
        $this->assertStringContainsString('few_sources', $decision->reasons[0]);
    }

    public function testShortContentBlocked(): void
    {
        $this->configureEnabled();
        $this->sensitiveDetector->method('isSensitive')->willReturn(false);

        $article = $this->createAiArticle(0.90, 5, 50);

        $decision = $this->gate->evaluate($article);

        $this->assertFalse($decision->canPublish);
        $this->assertTrue(\count(array_filter($decision->reasons, fn ($r) => str_starts_with($r, 'short_content'))) > 0);
    }

    public function testDiscrepancyBlocked(): void
    {
        $this->configureEnabled();
        $this->sensitiveDetector->method('isSensitive')->willReturn(false);

        $article = $this->createAiArticle(0.90, 5, 500);
        $article->setContent($article->getContent() . ' [DISCREPANȚĂ] Some issue here.');

        $decision = $this->gate->evaluate($article);

        $this->assertFalse($decision->canPublish);
        $this->assertContains('has_discrepancy', $decision->reasons);
    }

    public function testSensitiveTopicBlocked(): void
    {
        $this->configureEnabled();
        $this->sensitiveDetector->method('isSensitive')->willReturn(true);
        $this->sensitiveDetector->method('getSensitiveTopics')->willReturn(['politica']);

        $article = $this->createAiArticle(0.90, 5, 500);

        $decision = $this->gate->evaluate($article);

        $this->assertFalse($decision->canPublish);
        $this->assertStringContainsString('sensitive_topic', $decision->reasons[0] ?? '');
    }

    public function testNonAiWithHighCredibilityAutoPublishes(): void
    {
        $this->configureEnabled();
        $this->sensitiveDetector->method('isSensitive')->willReturn(false);

        $article = $this->createNonAiArticle(500);

        $source = new Source();
        $source->setName('Trusted Source');
        $source->setCredibilityWeight(0.95);

        $pr = $this->createMock(PressRelease::class);
        $pr->method('getSource')->willReturn($source);

        $decision = $this->gate->evaluate($article, $pr);

        $this->assertTrue($decision->canPublish);
    }

    public function testNonAiWithLowCredibilityBlocked(): void
    {
        $this->configureEnabled();
        $this->sensitiveDetector->method('isSensitive')->willReturn(false);

        $article = $this->createNonAiArticle(500);

        $source = new Source();
        $source->setName('Unknown Source');
        $source->setCredibilityWeight(0.3);

        $pr = $this->createMock(PressRelease::class);
        $pr->method('getSource')->willReturn($source);

        $decision = $this->gate->evaluate($article, $pr);

        $this->assertFalse($decision->canPublish);
        $this->assertStringContainsString('low_credibility', $decision->reasons[0]);
    }

    private function configureEnabled(): void
    {
        $this->settings->method('getBool')
            ->willReturnCallback(fn (string $key, bool $default) => $key === 'auto_publish.enabled' ? true : $default);
        $this->settings->method('getFloat')
            ->willReturnCallback(fn (string $key, float $default) => $default);
        $this->settings->method('getInt')
            ->willReturnCallback(fn (string $key, int $default) => $default);
    }

    private function createAiArticle(float $confidence, int $sources, int $wordCount): Article
    {
        $article = new Article();
        $article->setTitle('Test AI Article');
        $article->setAiGenerated(true);
        $article->setAiConfidenceScore($confidence);
        $article->setAiSourceCount($sources);
        $article->setContent($this->generateContent($wordCount));

        return $article;
    }

    private function createNonAiArticle(int $wordCount): Article
    {
        $article = new Article();
        $article->setTitle('Test Article');
        $article->setContent($this->generateContent($wordCount));

        return $article;
    }

    private function generateContent(int $wordCount): string
    {
        return implode(' ', array_fill(0, $wordCount, 'word'));
    }
}
