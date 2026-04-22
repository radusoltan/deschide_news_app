<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Topic;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentResponse;
use App\Dto\Topic\TopicDetectionResult;
use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Enum\LlmModelTier;
use App\Enum\TopicDetectionMethod;
use App\Repository\TopicRepository;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Ai\TierResolver;
use App\Service\Topic\PressReleaseTopicDetector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PressReleaseTopicDetectorTest extends TestCase
{
    private PressReleaseTopicDetector $detector;
    private TopicRepository&MockObject $topicRepo;
    private EntityManagerInterface&MockObject $em;
    private AgentDispatcher&MockObject $dispatcher;
    private TierResolver&MockObject $tierResolver;
    private GeminiCliService&MockObject $geminiCli;

    protected function setUp(): void
    {
        $this->topicRepo = $this->createMock(TopicRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->tierResolver = $this->createMock(TierResolver::class);
        $this->geminiCli = $this->createMock(GeminiCliService::class);

        $this->tierResolver->method('resolve')->willReturn(LlmModelTier::HAIKU);

        $this->detector = new PressReleaseTopicDetector(
            $this->topicRepo,
            $this->em,
            $this->dispatcher,
            $this->tierResolver,
            $this->geminiCli,
            new NullLogger(),
        );
    }

    private function agentResponse(string $content): AgentResponse
    {
        return new AgentResponse(
            content: $content,
            agentId: PressReleaseTopicDetector::AGENT_ID,
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: '01JXXXXXXXXXXXXXXXXXXXXXXX',
            metrics: null,
        );
    }

    public function testKeywordMatchReturnsResultsForMatchingTopics(): void
    {
        $pr = $this->createPressRelease(
            'Parlamentul a adoptat legea energiei',
            'Deputații au votat noi reglementări în domeniul energetic.',
        );

        $topic = $this->createTopic(1, ['energie', 'energetic', 'gaz', 'electricitate']);

        $this->topicRepo->method('findBy')
            ->willReturn([$topic]);

        $results = $this->detector->matchKeywords($pr);

        $this->assertNotEmpty($results);
        $this->assertSame(1, $results[0]->topicId);
        $this->assertSame(TopicDetectionMethod::KEYWORD, $results[0]->detectedBy);
        $this->assertGreaterThan(0.0, $results[0]->confidence);
    }

    public function testKeywordMatchHandlesRomanianDiacritics(): void
    {
        $pr = $this->createPressRelease(
            'Președintele a semnat decretul privind energia',
            'Țara se confruntă cu provocări politice și economice.',
        );

        // Use keywords that exactly match tokens in the text
        $topic = $this->createTopic(2, ['președintele', 'decretul', 'politice', 'economice']);

        $this->topicRepo->method('findBy')
            ->willReturn([$topic]);

        $results = $this->detector->matchKeywords($pr);

        $this->assertNotEmpty($results);
        $this->assertSame(2, $results[0]->topicId);
    }

    public function testKeywordMatchNormalizesCedillaToBelowComma(): void
    {
        // Content uses cedilla ş (U+015F) and ţ (U+0163)
        $pr = $this->createPressRelease(
            "Pre\u{015F}edintele a anun\u{0163}at reforme",
            "Reforma economic\u{0103} continu\u{0103}. Pre\u{015F}edintele decide.",
        );

        // Keywords use comma-below ș (U+0219) — should still match cedilla forms
        $topic = $this->createTopic(3, ["pre\u{0219}edintele", 'reforme', 'reforma']);

        $this->topicRepo->method('findBy')
            ->willReturn([$topic]);

        $results = $this->detector->matchKeywords($pr);

        // Should match despite cedilla vs comma-below difference
        $this->assertNotEmpty($results);
    }

    public function testKeywordMatchReturnsEmptyForNoMatches(): void
    {
        $pr = $this->createPressRelease('Sport: fotbal internațional', 'Liga Campionilor continuă.');

        $topic = $this->createTopic(4, ['energie', 'gaz', 'electricitate']);

        $this->topicRepo->method('findBy')
            ->willReturn([$topic]);

        $results = $this->detector->matchKeywords($pr);

        $this->assertEmpty($results);
    }

    public function testKeywordMatchSkipsTopicsWithoutKeywords(): void
    {
        $pr = $this->createPressRelease('Test', 'Content');

        $topic = $this->createTopic(5, null);

        $this->topicRepo->method('findBy')
            ->willReturn([$topic]);

        $results = $this->detector->matchKeywords($pr);

        $this->assertEmpty($results);
    }

    public function testKeywordMatchLimitsToMaxTopics(): void
    {
        $pr = $this->createPressRelease(
            'energie gaz politică economie sport educație sănătate transport infrastructură cultură',
            'Un text cu multe subiecte diferite.',
        );

        $topics = [];
        for ($i = 1; $i <= 10; $i++) {
            $keyword = match ($i) {
                1 => 'energie', 2 => 'gaz', 3 => 'politică', 4 => 'economie',
                5 => 'sport', 6 => 'educație', 7 => 'sănătate', 8 => 'transport',
                9 => 'infrastructură', 10 => 'cultură',
                default => 'other',
            };
            $topics[] = $this->createTopic($i, [$keyword]);
        }

        $this->topicRepo->method('findBy')
            ->willReturn($topics);

        $results = $this->detector->matchKeywords($pr);

        $this->assertLessThanOrEqual(5, \count($results));
    }

    public function testDetectTriggersLlmWhenKeywordConfidenceLow(): void
    {
        $pr = $this->createPressRelease('Subiect ambiguu', 'Un text fără cuvinte-cheie clare.');

        $this->topicRepo->method('findBy')
            ->willReturn([]);

        $this->topicRepo->method('getFullTree')
            ->willReturn([['id' => 1, 'title' => 'Politică', 'children' => []]]);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturn($this->agentResponse('[{"topic_id": 1, "confidence": 0.85}]'));

        $this->geminiCli->expects($this->never())
            ->method('execute');

        $results = $this->detector->detect($pr, useGeminiFallback: true);

        $this->assertNotEmpty($results);
        $this->assertSame(TopicDetectionMethod::LLM, $results[0]->detectedBy);
    }

    public function testDetectFallsBackToGeminiOnDispatcherFailure(): void
    {
        $pr = $this->createPressRelease('Subiect ambiguu', 'Un text fără cuvinte-cheie clare.');

        $this->topicRepo->method('findBy')
            ->willReturn([]);

        $this->topicRepo->method('getFullTree')
            ->willReturn([['id' => 1, 'title' => 'Politică', 'children' => []]]);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('retry exhausted'));

        $this->geminiCli->expects($this->once())
            ->method('execute')
            ->willReturn('[{"topic_id": 1, "confidence": 0.85}]');

        $results = $this->detector->detect($pr, useGeminiFallback: true);

        $this->assertNotEmpty($results);
        $this->assertSame(TopicDetectionMethod::LLM, $results[0]->detectedBy);
    }

    public function testEmergencyHaltSkipsGeminiFallback(): void
    {
        $pr = $this->createPressRelease('Subiect ambiguu', 'Un text fără cuvinte-cheie clare.');

        $this->topicRepo->method('findBy')
            ->willReturn([]);

        $this->topicRepo->method('getFullTree')
            ->willReturn([['id' => 1, 'title' => 'Politică', 'children' => []]]);

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new EmergencyHaltException(PressReleaseTopicDetector::AGENT_ID));

        // Halt must propagate past the fallback — Gemini MUST NOT be called.
        $this->geminiCli->expects($this->never())
            ->method('execute');

        $results = $this->detector->detect($pr, useGeminiFallback: true);

        // Fail-open: halt resolves to keyword-only (empty here → []).
        $this->assertEmpty($results);
    }

    public function testDetectSkipsLlmWhenKeywordConfidenceHigh(): void
    {
        $pr = $this->createPressRelease(
            'Energie energie energie gaz gaz electricitate',
            'Reforma energetică în Moldova. Prețul gazului crește.',
        );

        $topic = $this->createTopic(1, ['energie', 'energetic', 'gaz', 'electricitate', 'preț']);

        $this->topicRepo->method('findBy')
            ->willReturn([$topic]);

        $this->dispatcher->expects($this->never())
            ->method('dispatch');
        $this->geminiCli->expects($this->never())
            ->method('execute');

        $results = $this->detector->detect($pr, useGeminiFallback: true);

        $this->assertNotEmpty($results);
        $this->assertSame(TopicDetectionMethod::KEYWORD, $results[0]->detectedBy);
    }

    public function testDetectDegradedModeWhenBothDispatcherAndFallbackFail(): void
    {
        $pr = $this->createPressRelease('Subiect', 'Text scurt.');

        $this->topicRepo->method('findBy')
            ->willReturn([]);

        $this->topicRepo->method('getFullTree')
            ->willReturn([]);

        $this->dispatcher->method('dispatch')
            ->willThrowException(new \RuntimeException('retry exhausted'));
        $this->geminiCli->method('execute')
            ->willThrowException(new \RuntimeException('Gemini timeout'));

        $results = $this->detector->detect($pr, useGeminiFallback: true);

        // Should return empty (keyword layer had nothing) but NOT throw
        $this->assertEmpty($results);
    }

    public function testDetectWithoutLlmFallback(): void
    {
        $pr = $this->createPressRelease('Subiect', 'Text.');

        $this->topicRepo->method('findBy')
            ->willReturn([]);

        $this->dispatcher->expects($this->never())
            ->method('dispatch');
        $this->geminiCli->expects($this->never())
            ->method('execute');

        $results = $this->detector->detect($pr, useGeminiFallback: false);

        $this->assertEmpty($results);
    }

    public function testMultiWordKeywordMatch(): void
    {
        $pr = $this->createPressRelease(
            'Republica Moldova semnează acord',
            'Republica Moldova a semnat un acord important cu UE.',
        );

        $topic = $this->createTopic(10, ['Republica Moldova', 'acord', 'UE']);

        $this->topicRepo->method('findBy')
            ->willReturn([$topic]);

        $results = $this->detector->matchKeywords($pr);

        $this->assertNotEmpty($results);
        $this->assertGreaterThan(0.3, $results[0]->confidence);
    }

    public function testConfidenceCalculationWeightsPositionAndFrequency(): void
    {
        // Title mentions keyword (position bonus) + content repeats it (frequency bonus)
        $pr = $this->createPressRelease(
            'Energia regenerabilă în Moldova',
            'Sectorul energetic se dezvoltă. Energia solară și energia eoliană cresc.',
        );

        // Use exact token forms from the text
        $topic = $this->createTopic(1, ['energia', 'energetic', 'regenerabilă', 'solară', 'eoliană']);

        $this->topicRepo->method('findBy')
            ->willReturn([$topic]);

        $results = $this->detector->matchKeywords($pr);

        $this->assertNotEmpty($results);
        // Multiple keyword matches + position/frequency should give decent confidence
        $this->assertGreaterThan(0.2, $results[0]->confidence);
    }

    public function testEmptyContent(): void
    {
        $pr = $this->createPressRelease('', '');

        $this->topicRepo->method('findBy')
            ->willReturn([]);

        $results = $this->detector->matchKeywords($pr);

        $this->assertEmpty($results);
    }

    // -- Helpers --

    private function createPressRelease(string $title, string $content): PressRelease
    {
        $pr = new PressRelease();
        $pr->setTitle($title);
        $pr->setContent($content);
        $pr->setCategorySlug('general');

        return $pr;
    }

    private function createTopic(int $id, ?array $keywords): Topic
    {
        $topic = new Topic();
        $topic->setTitle("Topic {$id}");
        $topic->setIsActive(true);
        $topic->setKeywords($keywords);

        // Use reflection to set the ID
        $ref = new \ReflectionProperty(Topic::class, 'id');
        $ref->setValue($topic, $id);

        return $topic;
    }
}
