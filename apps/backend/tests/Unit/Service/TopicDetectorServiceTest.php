<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentResponse;
use App\Entity\Topic;
use App\Enum\LlmModelTier;
use App\Repository\TopicRepository;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Ai\TierResolver;
use App\Service\TopicDetectorService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Unit test for {@see TopicDetectorService}. New file added in T57.P6 —
 * the service was previously covered only indirectly via
 * ProcessScrapedArticleHandlerTest + TopicHashResolverTest. This closes
 * the C-P6 gap surfaced by Discovery and locks in dispatcher-first /
 * Gemini-fallback behaviour before future modifications.
 */
class TopicDetectorServiceTest extends TestCase
{
    private TopicDetectorService $service;
    private TopicRepository&MockObject $topicRepo;
    private AgentDispatcher&MockObject $dispatcher;
    private TierResolver&MockObject $tierResolver;
    private GeminiCliService&MockObject $geminiCli;

    protected function setUp(): void
    {
        $this->topicRepo = $this->createMock(TopicRepository::class);
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->tierResolver = $this->createMock(TierResolver::class);
        $this->geminiCli = $this->createMock(GeminiCliService::class);

        $this->tierResolver->method('resolve')->willReturn(LlmModelTier::HAIKU);

        $this->service = new TopicDetectorService(
            $this->topicRepo,
            $this->dispatcher,
            $this->tierResolver,
            $this->geminiCli,
            new NullLogger(),
            '/tmp',
        );
    }

    public function testReturnsEmptyWhenTopicTreeIsEmpty(): void
    {
        $this->topicRepo->method('getFullTree')->willReturn([]);
        $this->dispatcher->expects($this->never())->method('dispatch');
        $this->geminiCli->expects($this->never())->method('execute');

        $result = $this->service->detectTopics('Titlu', 'Lead');

        $this->assertSame([], $result);
    }

    public function testHappyPathViaDispatcher(): void
    {
        $this->seedSimpleTree();

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturn($this->agentResponse(
                '[{"topicId": 1, "confidence": "high", "reason": "Text despre politică"}]',
            ));
        $this->geminiCli->expects($this->never())->method('execute');

        $topic = $this->createMock(Topic::class);
        $this->topicRepo->method('find')->with(1)->willReturn($topic);

        $result = $this->service->detectTopics('Alegeri', 'Parlamentul moldovean');

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['topicId']);
        $this->assertSame('high', $result[0]['confidence']);
    }

    public function testFallsBackToGeminiOnDispatcherFailure(): void
    {
        $this->seedSimpleTree();

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('retry exhausted'));
        $this->geminiCli->expects($this->once())
            ->method('execute')
            ->willReturn('[{"topicId": 1, "confidence": "medium", "reason": "Tangential"}]');

        $topic = $this->createMock(Topic::class);
        $this->topicRepo->method('find')->with(1)->willReturn($topic);

        $result = $this->service->detectTopics('Titlu', 'Lead');

        $this->assertCount(1, $result);
        $this->assertSame('medium', $result[0]['confidence']);
    }

    public function testReturnsEmptyWhenBothDispatcherAndFallbackFail(): void
    {
        $this->seedSimpleTree();

        $this->dispatcher->method('dispatch')
            ->willThrowException(new \RuntimeException('retry exhausted'));
        $this->geminiCli->method('execute')
            ->willThrowException(new \RuntimeException('Gemini timeout'));

        $result = $this->service->detectTopics('Titlu', 'Lead');

        $this->assertSame([], $result);
    }

    public function testEmergencyHaltSkipsGeminiFallback(): void
    {
        $this->seedSimpleTree();

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->willThrowException(new EmergencyHaltException(TopicDetectorService::AGENT_ID));

        // Halt must propagate past the fallback — Gemini MUST NOT be called.
        $this->geminiCli->expects($this->never())->method('execute');

        $result = $this->service->detectTopics('Titlu', 'Lead');

        $this->assertSame([], $result);
    }

    public function testReturnsEmptyOnMalformedJson(): void
    {
        $this->seedSimpleTree();

        $this->dispatcher->method('dispatch')
            ->willReturn($this->agentResponse('not-json-at-all'));

        $result = $this->service->detectTopics('Titlu', 'Lead');

        $this->assertSame([], $result);
    }

    public function testFiltersOutInvalidTopicIds(): void
    {
        $this->seedSimpleTree();

        $this->dispatcher->method('dispatch')
            ->willReturn($this->agentResponse(
                '[{"topicId": 1, "confidence": "high", "reason": "ok"},'
                . '{"topicId": 999, "confidence": "high", "reason": "invalid"}]',
            ));

        // Topic 1 exists, 999 does not
        $topic = $this->createMock(Topic::class);
        $this->topicRepo->method('find')
            ->willReturnCallback(fn (int $id) => $id === 1 ? $topic : null);

        $result = $this->service->detectTopics('Titlu', 'Lead');

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['topicId']);
    }

    public function testFiltersOutEntriesWithoutRequiredFields(): void
    {
        $this->seedSimpleTree();

        $this->dispatcher->method('dispatch')
            ->willReturn($this->agentResponse(
                '[{"topicId": 1, "confidence": "high", "reason": "ok"},'
                . '{"topicId": 2},'
                . '{"confidence": "high"}]',
            ));

        $topic = $this->createMock(Topic::class);
        $this->topicRepo->method('find')->willReturn($topic);

        $result = $this->service->detectTopics('Titlu', 'Lead');

        $this->assertCount(1, $result);
    }

    public function testSortsByConfidenceHighMediumLow(): void
    {
        $this->seedSimpleTree();

        $this->dispatcher->method('dispatch')
            ->willReturn($this->agentResponse(
                '[{"topicId": 1, "confidence": "low", "reason": "a"},'
                . '{"topicId": 2, "confidence": "high", "reason": "b"},'
                . '{"topicId": 3, "confidence": "medium", "reason": "c"}]',
            ));

        $topic = $this->createMock(Topic::class);
        $this->topicRepo->method('find')->willReturn($topic);

        $result = $this->service->detectTopics('Titlu', 'Lead');

        $this->assertCount(3, $result);
        $this->assertSame('high', $result[0]['confidence']);
        $this->assertSame('medium', $result[1]['confidence']);
        $this->assertSame('low', $result[2]['confidence']);
    }

    public function testCapsResultsAtFiveEntries(): void
    {
        $this->seedSimpleTree();

        $items = [];
        for ($i = 1; $i <= 8; $i++) {
            $items[] = \sprintf(
                '{"topicId": %d, "confidence": "high", "reason": "r%d"}',
                $i,
                $i,
            );
        }
        $this->dispatcher->method('dispatch')
            ->willReturn($this->agentResponse('[' . implode(',', $items) . ']'));

        $topic = $this->createMock(Topic::class);
        $this->topicRepo->method('find')->willReturn($topic);

        $result = $this->service->detectTopics('Titlu', 'Lead');

        $this->assertCount(5, $result);
    }

    public function testHandlesMarkdownFencedJsonResponse(): void
    {
        $this->seedSimpleTree();

        // Some models emit fenced JSON despite explicit instructions;
        // fallback parser in parseAndValidate pulls the array via regex.
        $this->dispatcher->method('dispatch')
            ->willReturn($this->agentResponse(
                "Here's the classification:\n"
                . "```json\n"
                . '[{"topicId": 1, "confidence": "high", "reason": "ok"}]' . "\n"
                . '```',
            ));

        $topic = $this->createMock(Topic::class);
        $this->topicRepo->method('find')->willReturn($topic);

        $result = $this->service->detectTopics('Titlu', 'Lead');

        $this->assertCount(1, $result);
    }

    private function seedSimpleTree(): void
    {
        $this->topicRepo->method('getFullTree')->willReturn([
            ['id' => 1, 'title' => 'Politică', 'children' => []],
            ['id' => 2, 'title' => 'Economie', 'children' => []],
            ['id' => 3, 'title' => 'Societate', 'children' => []],
        ]);
    }

    private function agentResponse(string $content): AgentResponse
    {
        return new AgentResponse(
            content: $content,
            agentId: TopicDetectorService::AGENT_ID,
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: '01JXXXXXXXXXXXXXXXXXXXXXXX',
            metrics: null,
        );
    }
}
