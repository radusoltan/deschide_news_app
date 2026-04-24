<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentResponse;
use App\Entity\Topic;
use App\Entity\TopicBriefing;
use App\Enum\BriefingCadence;
use App\Enum\BriefingStatus;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\AnthropicClientInterface;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Ai\TierResolver;
use App\Service\Editorial\TopicBriefingWriterService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TopicBriefingWriterServiceTest extends TestCase
{
    private TopicBriefingWriterService $writer;
    private AgentDispatcher&MockObject $dispatcher;
    private TierResolver&MockObject $tierResolver;
    private GeminiCliService&MockObject $geminiCli;
    private AnthropicClientInterface&MockObject $claudeCli;
    private AppSettingRepository&MockObject $settings;
    private EntityManagerInterface&MockObject $em;

    /** Cadence => map of resolved tier. Reflects the seeded fixture post-T57.P4+P5. */
    private const TIER_BY_AGENT = [
        TopicBriefingWriterService::AGENT_ID_DAILY => 'SONNET',
        TopicBriefingWriterService::AGENT_ID_HOURLY => 'HAIKU',
        TopicBriefingWriterService::AGENT_ID_WEEKLY => 'SONNET',
        TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH => 'SONNET',
    ];

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->tierResolver = $this->createMock(TierResolver::class);
        $this->geminiCli = $this->createMock(GeminiCliService::class);
        $this->claudeCli = $this->createMock(AnthropicClientInterface::class);
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->settings->method('getInt')
            ->willReturnCallback(static fn (string $key, int $default): int => $default);
        $this->settings->method('getBool')
            ->willReturnCallback(static fn (string $key, bool $default): bool => $default);

        $this->tierResolver->method('resolve')
            ->willReturnCallback(static fn (string $agentId): LlmModelTier => match (self::TIER_BY_AGENT[$agentId] ?? null) {
                'HAIKU' => LlmModelTier::HAIKU,
                'SONNET' => LlmModelTier::SONNET,
                default => throw new \LogicException('Unknown agent id in test fixture: ' . $agentId),
            });

        $this->writer = $this->buildWriter();
    }

    #[Test]
    public function itReturnsNullWhenNoPressReleases(): void
    {
        $this->mockPrQuery([]);
        $this->dispatcher->expects($this->never())->method('dispatch');

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::DAILY, DateRange::lastDay());

        $this->assertNull($result);
    }

    #[Test]
    #[DataProvider('allCadences')]
    public function itDispatchesDraftUnderCadenceResolvedAgentId(BriefingCadence $cadence): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $expectedAgentId = match ($cadence) {
            BriefingCadence::DAILY => TopicBriefingWriterService::AGENT_ID_DAILY,
            BriefingCadence::HOURLY => TopicBriefingWriterService::AGENT_ID_HOURLY,
            BriefingCadence::WEEKLY => TopicBriefingWriterService::AGENT_ID_WEEKLY,
        };
        $expectedTier = $cadence === BriefingCadence::HOURLY ? LlmModelTier::HAIKU : LlmModelTier::SONNET;

        $draftResponse = $this->agentResponse($this->validBriefingJson('Draft'), $expectedAgentId, $expectedTier);

        $seenCalls = [];
        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function ($request) use (&$seenCalls, $draftResponse): AgentResponse {
                $seenCalls[] = [$request->agentId, $request->tier];
                if ($request->agentId === TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH) {
                    return $this->agentResponse(
                        $this->validBriefingJson('Polished'),
                        TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH,
                        LlmModelTier::SONNET,
                    );
                }

                return $draftResponse;
            });

        $result = $this->writer->generate($this->createTopic(), $cadence, DateRange::forCadence($cadence));

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertSame($expectedAgentId, $seenCalls[0][0]);
        $this->assertSame($expectedTier, $seenCalls[0][1]);
    }

    #[Test]
    public function itProducesPolishedBriefingForHourlyViaDispatcher(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $draftJson = $this->validBriefingJson('Haiku draft');
        $polishedJson = $this->validBriefingJson('Sonnet polish');

        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function ($request) use ($draftJson, $polishedJson): AgentResponse {
                return $request->agentId === TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH
                    ? $this->agentResponse($polishedJson, TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH, LlmModelTier::SONNET)
                    : $this->agentResponse($draftJson, TopicBriefingWriterService::AGENT_ID_HOURLY, LlmModelTier::HAIKU);
            });

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::HOURLY, DateRange::lastHour());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertTrue($result->isClaudePolished());
        $this->assertSame(BriefingStatus::POLISHED, $result->getStatus());
        $this->assertSame('Sonnet polish', $result->getTitle());
    }

    #[Test]
    #[DataProvider('cadencesWithoutPolish')]
    public function itDoesNotInvokePolishForDailyOrWeekly(BriefingCadence $cadence): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $seenAgentIds = [];
        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function ($request) use (&$seenAgentIds): AgentResponse {
                $seenAgentIds[] = $request->agentId;

                return $this->agentResponse(
                    $this->validBriefingJson('Draft only'),
                    $request->agentId,
                    $request->tier,
                );
            });

        // Claude direct client is legacy-path only; for dispatcher path this
        // also codifies that we don't accidentally fall through to it.
        $this->claudeCli->expects($this->never())->method('chat');

        $result = $this->writer->generate($this->createTopic(), $cadence, DateRange::forCadence($cadence));

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertSame(BriefingStatus::DRAFT, $result->getStatus());
        $this->assertFalse($result->isClaudePolished());
        $this->assertNotContains(
            TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH,
            $seenAgentIds,
            'Polish agent id must not be invoked for DAILY/WEEKLY',
        );
    }

    #[Test]
    #[DataProvider('allCadences')]
    public function itMarksFailedWhenEmergencyHaltThrownOnDraft(BriefingCadence $cadence): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $this->dispatcher->method('dispatch')
            ->willThrowException(new EmergencyHaltException($cadence->value));

        // Halt must propagate past the Gemini fallback even for HOURLY.
        $this->geminiCli->expects($this->never())->method('execute');

        $result = $this->writer->generate($this->createTopic(), $cadence, DateRange::forCadence($cadence));

        $this->assertNull($result);
    }

    #[Test]
    public function itFallsBackToGeminiOnHourlyDispatcherFailure(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $geminiDraftJson = $this->validBriefingJson('Gemini fallback draft');
        $polishedJson = $this->validBriefingJson('Polished from Gemini draft');

        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function ($request) use ($polishedJson): AgentResponse {
                if ($request->agentId === TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH) {
                    return $this->agentResponse($polishedJson, TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH, LlmModelTier::SONNET);
                }
                throw new \RuntimeException('retry exhausted');
            });

        $this->geminiCli->expects($this->once())
            ->method('execute')
            ->willReturn($geminiDraftJson);

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::HOURLY, DateRange::lastHour());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertSame(BriefingStatus::POLISHED, $result->getStatus());
    }

    #[Test]
    public function itMarksFailedWhenHourlyDispatcherAndGeminiBothFail(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $this->dispatcher->method('dispatch')
            ->willThrowException(new \RuntimeException('retry exhausted'));
        $this->geminiCli->method('execute')
            ->willThrowException(new GeminiCliException('Gemini timeout'));

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::HOURLY, DateRange::lastHour());

        $this->assertNull($result);
    }

    #[Test]
    #[DataProvider('cadencesWithoutPolish')]
    public function itMarksFailedOnDraftFailureWithoutFallbackForDailyOrWeekly(BriefingCadence $cadence): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $this->dispatcher->method('dispatch')
            ->willThrowException(new \RuntimeException('retry exhausted'));

        // No fallback for these cadences — Gemini must NOT be called.
        $this->geminiCli->expects($this->never())->method('execute');

        $result = $this->writer->generate($this->createTopic(), $cadence, DateRange::forCadence($cadence));

        $this->assertNull($result);
    }

    #[Test]
    public function itMarksFailedWhenDraftResponseUnparseable(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $this->dispatcher->method('dispatch')
            ->willReturn($this->agentResponse('not json at all', TopicBriefingWriterService::AGENT_ID_DAILY, LlmModelTier::SONNET));

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::DAILY, DateRange::lastDay());

        $this->assertNull($result);
    }

    #[Test]
    public function itKeepsDraftWhenHourlyPolishDispatchFails(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $draftJson = $this->validBriefingJson('Haiku draft');

        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function ($request) use ($draftJson): AgentResponse {
                if ($request->agentId === TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH) {
                    throw new \RuntimeException('polish retry exhausted');
                }

                return $this->agentResponse($draftJson, TopicBriefingWriterService::AGENT_ID_HOURLY, LlmModelTier::HAIKU);
            });

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::HOURLY, DateRange::lastHour());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertSame(BriefingStatus::DRAFT, $result->getStatus(), 'Polish failure must not escalate to FAILED — DRAFT preserved (AC#10)');
        $this->assertFalse($result->isClaudePolished());
        $this->assertSame('Haiku draft', $result->getTitle());
    }

    #[Test]
    public function itKeepsDraftWhenHourlyPolishHalted(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $draftJson = $this->validBriefingJson('Haiku draft');

        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function ($request) use ($draftJson): AgentResponse {
                if ($request->agentId === TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH) {
                    throw new EmergencyHaltException(TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH);
                }

                return $this->agentResponse($draftJson, TopicBriefingWriterService::AGENT_ID_HOURLY, LlmModelTier::HAIKU);
            });

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::HOURLY, DateRange::lastHour());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertSame(BriefingStatus::DRAFT, $result->getStatus());
        $this->assertFalse($result->isClaudePolished());
    }

    #[Test]
    public function itKeepsDraftWhenHourlyPolishResponseUnparseable(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $draftJson = $this->validBriefingJson('Haiku draft');

        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function ($request) use ($draftJson): AgentResponse {
                if ($request->agentId === TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH) {
                    return $this->agentResponse('not json', TopicBriefingWriterService::AGENT_ID_HOURLY_POLISH, LlmModelTier::SONNET);
                }

                return $this->agentResponse($draftJson, TopicBriefingWriterService::AGENT_ID_HOURLY, LlmModelTier::HAIKU);
            });

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::HOURLY, DateRange::lastHour());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertSame(BriefingStatus::DRAFT, $result->getStatus());
        $this->assertFalse($result->isClaudePolished());
    }

    #[Test]
    public function itHandlesMarkdownFencedDraftResponse(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $json = $this->validBriefingJson('Fenced title');
        $this->dispatcher->method('dispatch')
            ->willReturn($this->agentResponse(
                "```json\n{$json}\n```",
                TopicBriefingWriterService::AGENT_ID_DAILY,
                LlmModelTier::SONNET,
            ));

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::DAILY, DateRange::lastDay());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertSame('Fenced title', $result->getTitle());
    }

    #[Test]
    public function itRoutesThroughLegacyGeminiPathWhenFlagSet(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->settings->method('getInt')
            ->willReturnCallback(static fn (string $key, int $default): int => $default);
        $this->settings->method('getBool')
            ->willReturnCallback(static fn (string $key, bool $default): bool => match ($key) {
                'briefing.llm.use_legacy_gemini_daily' => true,
                'briefing.llm.polish_enabled' => false,
                default => $default,
            });

        $this->writer = $this->buildWriter();

        $geminiJson = $this->validBriefingJson('Legacy Gemini draft');
        $this->geminiCli->expects($this->once())
            ->method('execute')
            ->willReturn($geminiJson);

        // Dispatcher must not be invoked when legacy flag is set.
        $this->dispatcher->expects($this->never())->method('dispatch');

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::DAILY, DateRange::lastDay());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertSame('Legacy Gemini draft', $result->getTitle());
    }

    #[Test]
    public function itLegacyPathMarksFailedWhenGeminiFails(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->settings->method('getInt')
            ->willReturnCallback(static fn (string $key, int $default): int => $default);
        $this->settings->method('getBool')
            ->willReturnCallback(static fn (string $key, bool $default): bool => match ($key) {
                'briefing.llm.use_legacy_gemini_hourly' => true,
                default => $default,
            });

        $this->writer = $this->buildWriter();

        $this->geminiCli->method('execute')
            ->willThrowException(new GeminiCliException('timeout'));
        $this->dispatcher->expects($this->never())->method('dispatch');

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::HOURLY, DateRange::lastHour());

        $this->assertNull($result);
    }

    #[Test]
    public function itLegacyPathRunsClaudePolishViaDirectClient(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));

        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->settings->method('getInt')
            ->willReturnCallback(static fn (string $key, int $default): int => $default);
        $this->settings->method('getBool')
            ->willReturnCallback(static fn (string $key, bool $default): bool => match ($key) {
                'briefing.llm.use_legacy_gemini_weekly' => true,
                'briefing.llm.polish_enabled' => true,
                default => $default,
            });

        $this->writer = $this->buildWriter();

        $this->geminiCli->method('execute')
            ->willReturn($this->validBriefingJson('Legacy draft'));
        $this->claudeCli->expects($this->once())
            ->method('chat')
            ->willReturn($this->validBriefingJson('Legacy polished'));

        // Dispatcher must not be invoked at all in legacy path.
        $this->dispatcher->expects($this->never())->method('dispatch');

        $result = $this->writer->generate($this->createTopic(), BriefingCadence::WEEKLY, DateRange::lastWeek());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertTrue($result->isClaudePolished());
        $this->assertSame(BriefingStatus::POLISHED, $result->getStatus());
        $this->assertSame('Legacy polished', $result->getTitle());
    }

    /**
     * @return iterable<string, array{BriefingCadence}>
     */
    public static function allCadences(): iterable
    {
        yield 'daily' => [BriefingCadence::DAILY];
        yield 'hourly' => [BriefingCadence::HOURLY];
        yield 'weekly' => [BriefingCadence::WEEKLY];
    }

    /**
     * @return iterable<string, array{BriefingCadence}>
     */
    public static function cadencesWithoutPolish(): iterable
    {
        yield 'daily' => [BriefingCadence::DAILY];
        yield 'weekly' => [BriefingCadence::WEEKLY];
    }

    private function buildWriter(): TopicBriefingWriterService
    {
        return new TopicBriefingWriterService(
            $this->dispatcher,
            $this->tierResolver,
            $this->geminiCli,
            $this->claudeCli,
            $this->settings,
            $this->em,
            new NullLogger(),
        );
    }

    private function agentResponse(string $content, string $agentId, LlmModelTier $tier): AgentResponse
    {
        return new AgentResponse(
            content: $content,
            agentId: $agentId,
            tier: $tier,
            model: $tier->toModelString(),
            attempts: 1,
            invocationId: '01JXXXXXXXXXXXXXXXXXXXXXXX',
            metrics: null,
        );
    }

    private function validBriefingJson(string $titlePrefix): string
    {
        return json_encode([
            'title' => $titlePrefix,
            'summary_short' => 'Scurt rezumat.',
            'summary_long' => 'Rezumat detaliat.',
            'why_it_matters' => 'Relevant pentru Moldova.',
            'key_facts' => ['Fapt 1', 'Fapt 2'],
        ], \JSON_UNESCAPED_UNICODE);
    }

    private function createTopic(): Topic
    {
        $topic = new Topic();
        $topic->setTitle('Politică externă');
        $topic->setIsActive(true);

        return $topic;
    }

    /**
     * @return list<object>
     */
    private function createMockPrList(int $count): array
    {
        $prs = [];
        for ($i = 0; $i < $count; $i++) {
            $pr = $this->createMock(\App\Entity\PressRelease::class);
            $pr->method('getTitle')->willReturn("PR Title {$i}");
            $pr->method('getContent')->willReturn("Content for press release {$i} with some details.");
            $pr->method('getSourceName')->willReturn('TestSource');
            $pr->method('getSource')->willReturn(null);
            $pr->method('getReceivedAt')->willReturn(new \DateTimeImmutable("-{$i} hours"));
            $prs[] = $pr;
        }

        return $prs;
    }

    /**
     * @param list<object> $results
     */
    private function mockPrQuery(array $results): void
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getResult'])
            ->getMock();
        $query->method('getResult')->willReturn($results);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')->willReturn($qb);
    }
}
