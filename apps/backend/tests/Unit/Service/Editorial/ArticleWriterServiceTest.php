<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Dto\Editorial\ArticleDraft;
use App\Entity\PressRelease;
use App\Entity\PressReleaseTopic;
use App\Entity\StoryCluster;
use App\Entity\Topic;
use App\Entity\TopicBriefing;
use App\Enum\TopicDetectionMethod;
use App\Repository\AppSettingRepository;
use App\Repository\TopicBriefingRepository;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\ArticleWriterService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ArticleWriterServiceTest extends TestCase
{
    private ArticleWriterService $service;
    private AppSettingRepository&MockObject $appSettings;
    private TopicBriefingRepository&MockObject $topicBriefingRepository;

    protected function setUp(): void
    {
        // /usr/bin/false always exits 1 — Gemini call will fail; prompt/parse logic remains testable
        $geminiCli = new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger());

        $this->appSettings = $this->createMock(AppSettingRepository::class);
        // Default AppSettings reads return the ADR-019 D1 baseline so existing
        // tests that don't override survive without per-test stubbing.
        $this->appSettings->method('getInt')->willReturnCallback(
            static fn (string $key, int $default = 0): int => match ($key) {
                'article_generation.min_pr_count' => 1,
                'article_generation.min_avg_content_length' => 1500,
                default => $default,
            },
        );
        $this->appSettings->method('getFloat')->willReturnCallback(
            static fn (string $key, float $default = 0.0): float => match ($key) {
                'article_generation.min_topic_relevance' => 2.0,
                default => $default,
            },
        );

        $this->topicBriefingRepository = $this->createMock(TopicBriefingRepository::class);
        // Default: no briefing exists; new path falls back to structural keyFacts
        $this->topicBriefingRepository->method('findLatestForTopic')->willReturn(null);

        $this->service = new ArticleWriterService(
            $geminiCli,
            new NullLogger(),
            $this->appSettings,
            $this->topicBriefingRepository,
        );
    }

    // ────────────────────────────────────────────────────────────────────
    //  Sprint 52 — Topic + Window path tests
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function writeArticleFromTopicWindowReturnsNullForEmptyPressReleases(): void
    {
        $topic = $this->makeTopic('test-topic-empty');

        $draft = $this->service->writeArticleFromTopicWindow(
            $topic,
            new \DateTimeImmutable('-24 hours'),
            new \DateTimeImmutable(),
            [],
        );

        $this->assertNull($draft);
    }

    #[Test]
    public function writeArticleFromTopicWindowReturnsNullOnGeminiFailure(): void
    {
        // /usr/bin/false in setUp guarantees Gemini fails
        $topic = $this->makeTopic('test-topic-gemini-fail');
        $prs = $this->makePressReleasesLinkedTo($topic, 3, 2000);

        $draft = $this->service->writeArticleFromTopicWindow(
            $topic,
            new \DateTimeImmutable('-24 hours'),
            new \DateTimeImmutable(),
            $prs,
        );

        $this->assertNull($draft);
    }

    #[Test]
    public function buildPromptForTopicWindowIncludesTopicTitleAndSlug(): void
    {
        $topic = $this->makeTopic('alegeri-prezidentiale-2026', 'Alegeri prezidențiale 2026');
        $prs = $this->makePressReleasesLinkedTo($topic, 2, 1000);

        $prompt = $this->invokePrivate('buildPromptForTopicWindow', $topic, $prs, null);

        $this->assertStringContainsString('Alegeri prezidențiale 2026', $prompt);
        $this->assertStringContainsString('alegeri-prezidentiale-2026', $prompt);
    }

    #[Test]
    public function buildPromptUsesTopicBriefingFieldsWhenPresent(): void
    {
        $topic = $this->makeTopic('briefing-topic');
        $prs = $this->makePressReleasesLinkedTo($topic, 2, 1000);
        $briefing = $this->makeTopicBriefing($topic, [
            'summaryShort' => 'BRIEFING-SHORT-MARKER',
            'summaryLong' => 'BRIEFING-LONG-MARKER',
            'whyItMatters' => 'BRIEFING-WHY-MARKER',
            'keyFacts' => ['BRIEFING-FACT-A', 'BRIEFING-FACT-B'],
        ]);

        $prompt = $this->invokePrivate('buildPromptForTopicWindow', $topic, $prs, $briefing);

        $this->assertStringContainsString('BRIEFING-SHORT-MARKER', $prompt);
        $this->assertStringContainsString('BRIEFING-LONG-MARKER', $prompt);
        $this->assertStringContainsString('BRIEFING-WHY-MARKER', $prompt);
        $this->assertStringContainsString('BRIEFING-FACT-A', $prompt);
        $this->assertStringContainsString('BRIEFING-FACT-B', $prompt);
        // Briefing-section header should appear, fallback header should NOT
        $this->assertStringContainsString('REZUMAT EDITORIAL', $prompt);
        $this->assertStringNotContainsString('extras structural', $prompt);
    }

    #[Test]
    public function buildPromptFallsBackToStructuralKeyFactsWhenNoBriefing(): void
    {
        $topic = $this->makeTopic('no-briefing-topic');
        $prs = $this->makePressReleasesLinkedTo(
            $topic,
            5,
            1000,
            titlePrefix: 'TITLU-PR-',
        );

        $prompt = $this->invokePrivate('buildPromptForTopicWindow', $topic, $prs, null);

        $this->assertStringContainsString('extras structural', $prompt);
        // Fallback uses the first 3 PR titles
        $this->assertStringContainsString('Sursa 1: TITLU-PR-0', $prompt);
        $this->assertStringContainsString('Sursa 2: TITLU-PR-1', $prompt);
        $this->assertStringContainsString('Sursa 3: TITLU-PR-2', $prompt);
        // 4th and 5th PR titles must NOT appear in the keyFacts fallback
        // (they may still appear in the SURSE section, so check the fallback section explicitly)
        $factsSectionStart = strpos($prompt, 'extras structural');
        $sourcesSectionStart = strpos($prompt, 'SURSE COMPLETE');
        $this->assertNotFalse($factsSectionStart);
        $this->assertNotFalse($sourcesSectionStart);
        $factsSection = substr($prompt, (int) $factsSectionStart, (int) $sourcesSectionStart - (int) $factsSectionStart);
        $this->assertStringNotContainsString('TITLU-PR-3', $factsSection);
        $this->assertStringNotContainsString('TITLU-PR-4', $factsSection);
    }

    #[Test]
    public function buildPromptIncludesPressReleaseSourcesSection(): void
    {
        $topic = $this->makeTopic('sources-test');
        $prs = $this->makePressReleasesLinkedTo($topic, 3, 1500, titlePrefix: 'PR-IN-PROMPT-');

        $prompt = $this->invokePrivate('buildPromptForTopicWindow', $topic, $prs, null);

        $this->assertStringContainsString('SURSE COMPLETE', $prompt);
        $this->assertStringContainsString('PR-IN-PROMPT-0', $prompt);
        $this->assertStringContainsString('PR-IN-PROMPT-1', $prompt);
        $this->assertStringContainsString('PR-IN-PROMPT-2', $prompt);
    }

    #[Test]
    public function calculateConfidenceUsesTopicRelevanceProxyAndBriefingPresence(): void
    {
        $topic = $this->makeTopic('confidence-test');
        $richPrs = $this->makePressReleasesLinkedTo($topic, 5, 3000);
        $thinPrs = $this->makePressReleasesLinkedTo($topic, 1, 100);
        $briefing = $this->makeTopicBriefing($topic, [
            'summaryShort' => 'present',
            'summaryLong' => 'present',
            'keyFacts' => ['fact'],
        ]);

        $parsed = ['content' => str_repeat('word ', 500)];

        $richScore = $this->invokePrivate('calculateConfidenceForTopicWindow', $topic, $richPrs, $briefing, $parsed);
        $thinScore = $this->invokePrivate('calculateConfidenceForTopicWindow', $topic, $thinPrs, null, ['content' => 'short']);

        $this->assertGreaterThan($thinScore, $richScore);
        $this->assertLessThanOrEqual(1.0, $richScore);
        $this->assertGreaterThanOrEqual(0.0, $thinScore);
    }

    #[Test]
    public function getDetectionConfidenceReadsFromPressReleaseTopicLink(): void
    {
        $topic = $this->makeTopic('detection-confidence-test');
        $prs = $this->makePressReleasesLinkedTo($topic, 1, 1000, confidence: 0.93);
        $pr = $prs[0];

        $confidence = $this->invokePrivate('getDetectionConfidence', $pr, $topic);

        $this->assertEqualsWithDelta(0.93, $confidence, 0.0001);
    }

    #[Test]
    public function getDetectionConfidenceReturnsDefaultWhenNoLink(): void
    {
        $topic = $this->makeTopic('test-target');
        $unrelatedTopic = $this->makeTopic('test-other');
        $prs = $this->makePressReleasesLinkedTo($unrelatedTopic, 1, 1000);
        $pr = $prs[0];

        $confidence = $this->invokePrivate('getDetectionConfidence', $pr, $topic);

        $this->assertEqualsWithDelta(0.5, $confidence, 0.0001);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Eligibility — Topic + Window path
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function eligibleTopicWindowWithStrongInputs(): void
    {
        $topic = $this->makeTopic('eligible-test');
        $prs = $this->makePressReleasesLinkedTo($topic, 3, 2000, confidence: 0.9);

        $result = $this->service->isEligibleForTopicWindow($topic, $prs);

        $this->assertTrue($result['eligible']);
        $this->assertSame([], $result['reasons']);
        $this->assertSame(3, $result['prCount']);
        $this->assertEqualsWithDelta(0.9, $result['avgConfidence'], 0.0001);
        $this->assertGreaterThanOrEqual(1500, $result['avgContentLength']);
    }

    #[Test]
    public function ineligibleTopicWindowWhenZeroPrs(): void
    {
        $topic = $this->makeTopic('zero-pr-test');

        // Override min_pr_count to 1 (the default) and force zero PRs
        $result = $this->service->isEligibleForTopicWindow($topic, []);

        $this->assertFalse($result['eligible']);
        $this->assertNotEmpty($result['reasons']);
        $this->assertStringContainsString('Too few press releases', $result['reasons'][0]);
        $this->assertSame(0, $result['prCount']);
    }

    #[Test]
    public function ineligibleTopicWindowWhenRelevanceProxyTooLow(): void
    {
        $topic = $this->makeTopic('low-relevance-test');
        // 1 PR × 0.3 confidence = 0.3 relevance — below 2.0 default threshold
        $prs = $this->makePressReleasesLinkedTo($topic, 1, 2000, confidence: 0.3);

        $result = $this->service->isEligibleForTopicWindow($topic, $prs);

        $this->assertFalse($result['eligible']);
        $this->assertNotEmpty($result['reasons']);
        $relevanceReason = array_filter($result['reasons'], static fn (string $r) => str_contains($r, 'relevance'));
        $this->assertNotEmpty($relevanceReason, 'Expected a relevance-related rejection reason');
    }

    #[Test]
    public function ineligibleTopicWindowWhenContentTooThin(): void
    {
        $topic = $this->makeTopic('thin-content-test');
        // 3 PRs with high confidence (passes relevance) but tiny content (fails length)
        $prs = $this->makePressReleasesLinkedTo($topic, 3, 200, confidence: 0.9);

        $result = $this->service->isEligibleForTopicWindow($topic, $prs);

        $this->assertFalse($result['eligible']);
        $this->assertNotEmpty($result['reasons']);
        $thinReason = array_filter($result['reasons'], static fn (string $r) => str_contains($r, 'too thin'));
        $this->assertNotEmpty($thinReason, 'Expected a "too thin" rejection reason');
    }

    // ────────────────────────────────────────────────────────────────────
    //  Legacy StoryCluster path — unchanged behavioral contract
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function legacyGenerateArticleStillReturnsNullForEmptyCluster(): void
    {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Empty cluster');

        $draft = $this->service->generateArticle($cluster);

        $this->assertNull($draft);
    }

    #[Test]
    public function legacyGenerateArticleReturnsNullOnGeminiFailure(): void
    {
        $cluster = $this->createClusterWithPRs(3);

        $draft = $this->service->generateArticle($cluster);

        $this->assertNull($draft);
    }

    #[Test]
    public function legacyParseResponseHandlesValidJson(): void
    {
        $ref = new \ReflectionMethod($this->service, 'parseResponse');
        $result = $ref->invoke($this->service, $this->validJsonResponse());

        $this->assertNotNull($result);
        $this->assertSame('Titlul articolului generat', $result['title']);
        $this->assertSame('Meta description SEO pentru articol', $result['meta_description']);
        $this->assertCount(3, $result['suggested_tags']);
    }

    #[Test]
    public function legacyParseResponseStripsMarkdownFences(): void
    {
        $ref = new \ReflectionMethod($this->service, 'parseResponse');
        $wrapped = "```json\n" . $this->validJsonResponse() . "\n```";

        $result = $ref->invoke($this->service, $wrapped);

        $this->assertNotNull($result);
        $this->assertSame('Titlul articolului generat', $result['title']);
    }

    #[Test]
    public function legacyParseResponseReturnsNullForInvalidJson(): void
    {
        $ref = new \ReflectionMethod($this->service, 'parseResponse');

        $this->assertNull($ref->invoke($this->service, 'Not JSON'));
        $this->assertNull($ref->invoke($this->service, '{"only": "partial"}'));
    }

    #[Test]
    public function legacyParseResponseReturnsNullForEmptyRequiredFields(): void
    {
        $ref = new \ReflectionMethod($this->service, 'parseResponse');

        $json = json_encode([
            'title' => 'Has title',
            'lead' => '',
            'content' => 'Content',
            'meta_description' => 'Meta',
        ]);

        $this->assertNull($ref->invoke($this->service, $json));
    }

    #[Test]
    public function legacyParseResponseHandlesMissingSuggestedTags(): void
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
    public function legacyBuildPromptContainsClusterData(): void
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
    public function legacyExcerptsMarkedInPrompt(): void
    {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Mixed cluster');
        $cluster->setSummaryShort('Summary');

        $fullPr = new PressRelease();
        $fullPr->setTitle('Full PR');
        $fullPr->setContent(str_repeat('Full content text here. ', 30));
        $fullPr->setCategorySlug('externe');
        $fullPr->setSourceName('full_source');
        $cluster->addPressRelease($fullPr);

        $excerptPr = new PressRelease();
        $excerptPr->setTitle('Excerpt PR');
        $excerptPr->setContent('Short.');
        $excerptPr->setCategorySlug('externe');
        $excerptPr->setSourceName('excerpt_source');
        $cluster->addPressRelease($excerptPr);

        $cluster->recalculateCounts();

        $ref = new \ReflectionMethod($this->service, 'buildPrompt');
        $prompt = $ref->invoke($this->service, $cluster);

        $this->assertStringContainsString('[EXCERPT ONLY]', $prompt);
        $fullPos = strpos($prompt, 'Full PR');
        $excerptPos = strpos($prompt, 'Excerpt PR');
        $this->assertLessThan($excerptPos, $fullPos, 'Full-text sources should appear before excerpts');
    }

    #[Test]
    public function legacyConfidenceScoreCalculation(): void
    {
        $ref = new \ReflectionMethod($this->service, 'calculateConfidence');

        $richCluster = $this->createClusterWithPRs(5, 3000);
        $richScore = $ref->invoke($this->service, $richCluster, ['content' => str_repeat('word ', 500)]);

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

    // ────────────────────────────────────────────────────────────────────
    //  Diacritics — shared helper, signature now string label
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function checkDiacriticsWarnsCedillaWithStringLabel(): void
    {
        $logger = new class extends NullLogger {
            public array $warnings = [];

            public function warning(string|\Stringable $message, array $context = []): void
            {
                $this->warnings[] = (string) $message;
            }
        };

        $service = new ArticleWriterService(
            new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()),
            $logger,
            $this->appSettings,
            $this->topicBriefingRepository,
        );

        $ref = new \ReflectionMethod($service, 'checkDiacritics');
        $parsed = ['title' => 'Titlu cu cedilla: ş ţ', 'lead' => 'Lead', 'content' => 'Content'];

        $ref->invoke($service, $parsed, 'topic#42');

        $this->assertCount(1, $logger->warnings);
        $this->assertStringContainsString('CEDILLA', $logger->warnings[0]);
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

        $service = new ArticleWriterService(
            new GeminiCliService('/usr/bin/false', '/tmp', new NullLogger()),
            $logger,
            $this->appSettings,
            $this->topicBriefingRepository,
        );

        $ref = new \ReflectionMethod($service, 'checkDiacritics');
        $parsed = ['title' => 'Titlu corect cu ș și ț', 'lead' => 'Lead', 'content' => 'Content'];

        $ref->invoke($service, $parsed, 'topic#42');

        $this->assertCount(0, $logger->warnings);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Legacy StoryCluster eligibility — unchanged behavioral contract
    // ────────────────────────────────────────────────────────────────────

    #[Test]
    public function legacyEligibleClusterWithRichContent(): void
    {
        $cluster = $this->createClusterWithPRs(5, 2000);

        $result = $this->service->isEligibleForAiGeneration($cluster);

        $this->assertTrue($result['eligible']);
        $this->assertNull($result['reason']);
        $this->assertSame(5, $result['sourceCount']);
        $this->assertGreaterThanOrEqual(1500, $result['avgLength']);
    }

    #[Test]
    public function legacyIneligibleClusterTooFewSources(): void
    {
        $cluster = $this->createClusterWithPRs(2, 5000);

        $result = $this->service->isEligibleForAiGeneration($cluster);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Too few sources', $result['reason']);
        $this->assertSame(2, $result['sourceCount']);
    }

    #[Test]
    public function legacyIneligibleClusterThinContent(): void
    {
        $cluster = $this->createClusterWithPRs(5, 400);

        $result = $this->service->isEligibleForAiGeneration($cluster);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('Content too thin', $result['reason']);
    }

    #[Test]
    public function legacyIneligibleEmptyCluster(): void
    {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Empty');

        $result = $this->service->isEligibleForAiGeneration($cluster);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('No press releases', $result['reason']);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────────

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

    private function makeTopic(string $slug, ?string $title = null): Topic
    {
        $topic = new Topic();
        $topic->setTranslatableLocale('ro');
        $topic->setSlug($slug);
        $topic->setTitle($title ?? ('Topic ' . $slug));
        $topic->setIsActive(true);

        // Topic IDs are auto-generated; for unit tests we set via reflection
        // so getDetectionConfidence's id-equality check works.
        $idProp = new \ReflectionProperty(Topic::class, 'id');
        $idProp->setValue($topic, crc32($slug));

        return $topic;
    }

    /**
     * @return PressRelease[]
     */
    private function makePressReleasesLinkedTo(
        Topic $topic,
        int $count,
        int $contentLength,
        float $confidence = 0.9,
        string $titlePrefix = 'PR-',
    ): array {
        $prs = [];
        for ($i = 0; $i < $count; $i++) {
            $pr = new PressRelease();
            $pr->setTitle($titlePrefix . $i);
            $pr->setContent(str_repeat('Conținut. ', max(1, (int) ($contentLength / 10))));
            $pr->setCategorySlug('externe');
            $pr->setSourceName('source-' . $i);
            $pr->setDetectedLanguage('ro');

            $idProp = new \ReflectionProperty(PressRelease::class, 'id');
            $idProp->setValue($pr, $i + 1);

            $link = new PressReleaseTopic($pr, $topic, $confidence, TopicDetectionMethod::LLM);
            $pr->addPressReleaseTopic($link);

            $prs[] = $pr;
        }

        return $prs;
    }

    /**
     * @param array{summaryShort?: string, summaryLong?: string, whyItMatters?: string, keyFacts?: list<string>} $fields
     */
    private function makeTopicBriefing(Topic $topic, array $fields): TopicBriefing
    {
        $briefing = new TopicBriefing(
            $topic,
            \App\Enum\BriefingCadence::DAILY,
            new \DateTimeImmutable('-24 hours'),
            new \DateTimeImmutable(),
        );

        if (isset($fields['summaryShort'])) {
            $prop = new \ReflectionProperty(TopicBriefing::class, 'summaryShort');
            $prop->setValue($briefing, $fields['summaryShort']);
        }
        if (isset($fields['summaryLong'])) {
            $prop = new \ReflectionProperty(TopicBriefing::class, 'summaryLong');
            $prop->setValue($briefing, $fields['summaryLong']);
        }
        if (isset($fields['whyItMatters'])) {
            $prop = new \ReflectionProperty(TopicBriefing::class, 'whyItMatters');
            $prop->setValue($briefing, $fields['whyItMatters']);
        }
        if (isset($fields['keyFacts'])) {
            $prop = new \ReflectionProperty(TopicBriefing::class, 'keyFacts');
            $prop->setValue($briefing, $fields['keyFacts']);
        }

        return $briefing;
    }

    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($this->service, $method);

        return $ref->invoke($this->service, ...$args);
    }
}
