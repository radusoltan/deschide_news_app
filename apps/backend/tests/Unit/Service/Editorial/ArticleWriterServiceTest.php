<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Dto\Editorial\ArticleDraft;
use App\Entity\PressRelease;
use App\Entity\StoryCluster;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\ArticleWriterService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ArticleWriterServiceTest extends TestCase
{
    private ArticleWriterService $service;

    protected function setUp(): void
    {
        // /usr/bin/false always exits 1 — Gemini call will fail, but prompt/parse logic is testable
        $geminiCli = new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger());
        $this->service = new ArticleWriterService($geminiCli, new NullLogger());
    }

    private function createClusterWithPRs(int $prCount = 3, int $contentLength = 2000): StoryCluster
    {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Test Cluster Headline');
        $cluster->setSummaryShort('Short summary of the cluster.');
        $cluster->setSummaryMedium('Medium summary with more context about the cluster.');
        $cluster->setWhyItMatters('This matters for Moldova because...');
        $cluster->setKeyFacts(['Fact 1', 'Fact 2', 'Fact 3']);

        for ($i = 0; $i < $prCount; $i++) {
            $pr = new PressRelease();
            $pr->setTitle("Press Release #{$i}: Important news");
            $pr->setContent(str_repeat('Conținut important. ', (int) ($contentLength / 20)));
            $pr->setCategorySlug('externe');
            $pr->setSourceName("source_{$i}");
            $pr->setDetectedLanguage('ro');
            $cluster->addPressRelease($pr);
        }

        $cluster->recalculateCounts();

        return $cluster;
    }

    private function validJsonResponse(): string
    {
        return json_encode([
            'title' => 'Titlul articolului generat',
            'lead' => 'Lead-ul articolului cu maximum 50 de cuvinte care răspunde la întrebările cheie.',
            'content' => 'Conținutul complet al articolului. ' . str_repeat('Paragraf de text. ', 50),
            'meta_description' => 'Meta description SEO pentru articol',
            'suggested_tags' => ['tag1', 'tag2', 'tag3'],
        ], \JSON_UNESCAPED_UNICODE);
    }

    #[Test]
    public function returnsNullForEmptyCluster(): void
    {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Empty cluster');

        $draft = $this->service->generateArticle($cluster);

        $this->assertNull($draft);
    }

    #[Test]
    public function returnsNullOnGeminiFailure(): void
    {
        // /usr/bin/false always fails — Gemini call returns null
        $cluster = $this->createClusterWithPRs(3);

        $draft = $this->service->generateArticle($cluster);

        $this->assertNull($draft);
    }

    #[Test]
    public function parseResponseHandlesValidJson(): void
    {
        $ref = new \ReflectionMethod($this->service, 'parseResponse');
        $result = $ref->invoke($this->service, $this->validJsonResponse());

        $this->assertNotNull($result);
        $this->assertSame('Titlul articolului generat', $result['title']);
        $this->assertSame('Meta description SEO pentru articol', $result['meta_description']);
        $this->assertCount(3, $result['suggested_tags']);
    }

    #[Test]
    public function parseResponseStripsMarkdownFences(): void
    {
        $ref = new \ReflectionMethod($this->service, 'parseResponse');
        $wrapped = "```json\n" . $this->validJsonResponse() . "\n```";

        $result = $ref->invoke($this->service, $wrapped);

        $this->assertNotNull($result);
        $this->assertSame('Titlul articolului generat', $result['title']);
    }

    #[Test]
    public function parseResponseReturnsNullForInvalidJson(): void
    {
        $ref = new \ReflectionMethod($this->service, 'parseResponse');

        $this->assertNull($ref->invoke($this->service, 'Not JSON'));
        $this->assertNull($ref->invoke($this->service, '{"only": "partial"}'));
    }

    #[Test]
    public function parseResponseReturnsNullForEmptyRequiredFields(): void
    {
        $ref = new \ReflectionMethod($this->service, 'parseResponse');

        $json = json_encode([
            'title' => 'Has title',
            'lead' => '',  // Empty lead
            'content' => 'Content',
            'meta_description' => 'Meta',
        ]);

        $this->assertNull($ref->invoke($this->service, $json));
    }

    #[Test]
    public function parseResponseHandlesMissingSuggestedTags(): void
    {
        $ref = new \ReflectionMethod($this->service, 'parseResponse');

        $json = json_encode([
            'title' => 'Titlu',
            'lead' => 'Lead text',
            'content' => 'Content text',
            'meta_description' => 'Meta',
        ]);

        $result = $ref->invoke($this->service, $json);
        $this->assertNotNull($result);
        $this->assertSame([], $result['suggested_tags']);
    }

    #[Test]
    public function buildPromptContainsClusterData(): void
    {
        $cluster = $this->createClusterWithPRs(2, 1000);

        $ref = new \ReflectionMethod($this->service, 'buildPrompt');
        $prompt = $ref->invoke($this->service, $cluster);

        $this->assertStringContainsString('Test Cluster Headline', $prompt);
        $this->assertStringContainsString('Short summary of the cluster.', $prompt);
        $this->assertStringContainsString('This matters for Moldova', $prompt);
        $this->assertStringContainsString('Fact 1', $prompt);
        $this->assertStringContainsString('Press Release #0', $prompt);
    }

    #[Test]
    public function excertsMarkedInPrompt(): void
    {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Mixed cluster');
        $cluster->setSummaryShort('Summary');

        // Full text PR (>500 chars)
        $fullPr = new PressRelease();
        $fullPr->setTitle('Full PR');
        $fullPr->setContent(str_repeat('Full content text here. ', 30)); // ~720 chars
        $fullPr->setCategorySlug('externe');
        $fullPr->setSourceName('full_source');
        $cluster->addPressRelease($fullPr);

        // Excerpt PR (<500 chars)
        $excerptPr = new PressRelease();
        $excerptPr->setTitle('Excerpt PR');
        $excerptPr->setContent('Short.'); // 6 chars
        $excerptPr->setCategorySlug('externe');
        $excerptPr->setSourceName('excerpt_source');
        $cluster->addPressRelease($excerptPr);

        $cluster->recalculateCounts();

        $ref = new \ReflectionMethod($this->service, 'buildPrompt');
        $prompt = $ref->invoke($this->service, $cluster);

        $this->assertStringContainsString('[EXCERPT ONLY]', $prompt);
        // Full-text source should appear first (sorted by credibility)
        $fullPos = strpos($prompt, 'Full PR');
        $excerptPos = strpos($prompt, 'Excerpt PR');
        $this->assertLessThan($excerptPos, $fullPos, 'Full-text sources should appear before excerpts');
    }

    #[Test]
    public function confidenceScoreCalculation(): void
    {
        $ref = new \ReflectionMethod($this->service, 'calculateConfidence');

        // Rich cluster: 5 full-text PRs with all summary fields
        $richCluster = $this->createClusterWithPRs(5, 3000);

        $richScore = $ref->invoke($this->service, $richCluster, ['content' => str_repeat('word ', 500)]);

        // Thin cluster: 2 excerpt PRs without summary
        $thinCluster = new StoryCluster();
        $thinCluster->setPrimaryHeadline('Thin');
        for ($i = 0; $i < 2; $i++) {
            $pr = new PressRelease();
            $pr->setTitle("PR {$i}");
            $pr->setContent('Short');
            $pr->setCategorySlug('externe');
            $thinCluster->addPressRelease($pr);
        }
        $thinCluster->recalculateCounts();

        $thinScore = $ref->invoke($this->service, $thinCluster, ['content' => 'Short content']);

        $this->assertGreaterThan($thinScore, $richScore);
        $this->assertLessThanOrEqual(1.0, $richScore);
        $this->assertGreaterThanOrEqual(0.0, $thinScore);
    }

    #[Test]
    public function checkDiacriticsWarnsCedilla(): void
    {
        // Use a logger that captures warnings
        $logger = new class extends NullLogger {
            public array $warnings = [];
            public function warning(string|\Stringable $message, array $context = []): void
            {
                $this->warnings[] = (string) $message;
            }
        };

        $geminiCli = new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger());
        $service = new ArticleWriterService($geminiCli, $logger);

        $ref = new \ReflectionMethod($service, 'checkDiacritics');

        // Cedilla characters: ş (U+015F) and ţ (U+0163)
        $parsed = [
            'title' => "Titlu cu cedilla: ş ţ",
            'lead' => 'Lead',
            'content' => 'Content',
        ];

        $ref->invoke($service, $parsed, 42);

        $this->assertCount(1, $logger->warnings);
        $this->assertStringContainsString('CEDILLA', $logger->warnings[0]);
    }

    // --- Content-Depth Gate Tests ---

    #[Test]
    public function eligibleClusterWithRichContent(): void
    {
        $cluster = $this->createClusterWithPRs(5, 2000);

        $result = $this->service->isEligibleForAiGeneration($cluster);

        $this->assertTrue($result['eligible']);
        $this->assertNull($result['reason']);
        $this->assertSame(5, $result['sourceCount']);
        $this->assertGreaterThanOrEqual(1500, $result['avgLength']);
    }

    #[Test]
    public function ineligibleClusterTooFewSources(): void
    {
        $cluster = $this->createClusterWithPRs(2, 5000);

        $result = $this->service->isEligibleForAiGeneration($cluster);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Too few sources', $result['reason']);
        $this->assertSame(2, $result['sourceCount']);
    }

    #[Test]
    public function ineligibleClusterThinContent(): void
    {
        $cluster = $this->createClusterWithPRs(5, 400);

        $result = $this->service->isEligibleForAiGeneration($cluster);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Content too thin', $result['reason']);
    }

    #[Test]
    public function ineligibleEmptyCluster(): void
    {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Empty');

        $result = $this->service->isEligibleForAiGeneration($cluster);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('No press releases', $result['reason']);
    }

    #[Test]
    public function checkDiacriticsNoWarningForCommaBelow(): void
    {
        $logger = new class extends NullLogger {
            public array $warnings = [];
            public function warning(string|\Stringable $message, array $context = []): void
            {
                $this->warnings[] = (string) $message;
            }
        };

        $geminiCli = new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger());
        $service = new ArticleWriterService($geminiCli, $logger);

        $ref = new \ReflectionMethod($service, 'checkDiacritics');

        // Comma-below: ș (U+0219) and ț (U+021B)
        $parsed = [
            'title' => 'Titlu corect cu ș și ț',
            'lead' => 'Lead',
            'content' => 'Content',
        ];

        $ref->invoke($service, $parsed, 42);

        $this->assertCount(0, $logger->warnings);
    }
}
