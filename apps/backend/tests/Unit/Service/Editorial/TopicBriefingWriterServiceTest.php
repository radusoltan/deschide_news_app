<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Entity\Topic;
use App\Entity\TopicBriefing;
use App\Enum\BriefingCadence;
use App\Enum\BriefingStatus;
use App\Repository\AppSettingRepository;
use App\Service\Ai\AnthropicClientInterface;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\TopicBriefingWriterService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class TopicBriefingWriterServiceTest extends TestCase
{
    private TopicBriefingWriterService $writer;
    private GeminiCliService $geminiCli;
    private AnthropicClientInterface $claudeCli;
    private AppSettingRepository $settings;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->geminiCli = $this->createMock(GeminiCliService::class);
        $this->claudeCli = $this->createMock(AnthropicClientInterface::class);
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        // Default: all settings return defaults
        $this->settings->method('getInt')
            ->willReturnCallback(fn(string $key, int $default) => $default);
        $this->settings->method('getBool')
            ->willReturnCallback(fn(string $key, bool $default) => $default);

        $this->writer = new TopicBriefingWriterService(
            $this->geminiCli,
            $this->claudeCli,
            $this->settings,
            $this->em,
            new NullLogger(),
        );
    }

    #[Test]
    public function itReturnsNullWhenNoPressReleases(): void
    {
        $this->mockPrQuery([]);
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');

        $topic = $this->createTopic();
        $range = DateRange::lastDay();

        $result = $this->writer->generate($topic, BriefingCadence::DAILY, $range);

        $this->assertNull($result);
    }

    #[Test]
    public function itReturnsNullWhenGeminiFails(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');

        $this->geminiCli->method('execute')
            ->willThrowException(new GeminiCliException('timeout'));

        $topic = $this->createTopic();

        $result = $this->writer->generate($topic, BriefingCadence::DAILY, DateRange::lastDay());

        $this->assertNull($result);
    }

    #[Test]
    public function itReturnsNullWhenGeminiResponseUnparseable(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');

        $this->geminiCli->method('execute')
            ->willReturn('not json at all');

        $topic = $this->createTopic();

        $result = $this->writer->generate($topic, BriefingCadence::DAILY, DateRange::lastDay());

        $this->assertNull($result);
    }

    #[Test]
    public function itCreatesDraftBriefingFromGemini(): void
    {
        $this->mockPrQuery($this->createMockPrList(8));
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');

        $geminiResponse = json_encode([
            'title' => 'Test Briefing Title',
            'summary_short' => 'Scurt rezumat.',
            'summary_long' => 'Rezumat detaliat al evenimentelor.',
            'why_it_matters' => 'Relevant pentru Moldova.',
            'key_facts' => ['Fapt 1', 'Fapt 2', 'Fapt 3'],
        ], \JSON_UNESCAPED_UNICODE);

        $this->geminiCli->method('execute')->willReturn($geminiResponse);

        // Claude polish enabled by default, mock successful polish
        $this->claudeCli->method('chat')->willReturn($geminiResponse);

        $topic = $this->createTopic();

        $result = $this->writer->generate($topic, BriefingCadence::DAILY, DateRange::lastDay());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertSame('Test Briefing Title', $result->getTitle());
        $this->assertSame('Scurt rezumat.', $result->getSummaryShort());
        $this->assertSame(8, $result->getPrCount());
        $this->assertNotNull($result->getGeneratedAt());
    }

    #[Test]
    public function itFallsBackToGeminiDraftWhenClaudeFails(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');

        $geminiResponse = json_encode([
            'title' => 'Gemini Draft',
            'summary_short' => 'Draft rezumat.',
            'summary_long' => 'Draft detaliat.',
            'why_it_matters' => 'Context.',
            'key_facts' => ['Fapt A'],
        ], \JSON_UNESCAPED_UNICODE);

        $this->geminiCli->method('execute')->willReturn($geminiResponse);
        $this->claudeCli->method('chat')
            ->willThrowException(new \RuntimeException('Claude timeout'));

        $topic = $this->createTopic();

        $result = $this->writer->generate($topic, BriefingCadence::DAILY, DateRange::lastDay());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertFalse($result->isClaudePolished());
        $this->assertSame(BriefingStatus::DRAFT, $result->getStatus());
        $this->assertSame('Gemini Draft', $result->getTitle());
    }

    #[Test]
    public function itSkipsClaudeWhenPolishDisabled(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');

        // Override polish_enabled to false
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->settings->method('getInt')
            ->willReturnCallback(fn(string $key, int $default) => $default);
        $this->settings->method('getBool')
            ->willReturnCallback(fn(string $key, bool $default) => match ($key) {
                'briefing.llm.polish_enabled' => false,
                default => $default,
            });

        $this->writer = new TopicBriefingWriterService(
            $this->geminiCli,
            $this->claudeCli,
            $this->settings,
            $this->em,
            new NullLogger(),
        );

        $geminiResponse = json_encode([
            'title' => 'Draft Only',
            'summary_short' => 'Doar draft.',
            'summary_long' => 'Rezumat.',
            'why_it_matters' => 'Context.',
            'key_facts' => ['Fapt'],
        ], \JSON_UNESCAPED_UNICODE);

        $this->geminiCli->method('execute')->willReturn($geminiResponse);

        // Claude should NOT be called
        $this->claudeCli->expects($this->never())->method('chat');

        $topic = $this->createTopic();

        $result = $this->writer->generate($topic, BriefingCadence::DAILY, DateRange::lastDay());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertFalse($result->isClaudePolished());
        $this->assertSame(BriefingStatus::DRAFT, $result->getStatus());
    }

    #[Test]
    public function itHandlesGeminiResponseInMarkdownCodeBlock(): void
    {
        $this->mockPrQuery($this->createMockPrList(3));
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');

        // Disable polish to simplify test
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->settings->method('getInt')
            ->willReturnCallback(fn(string $key, int $default) => $default);
        $this->settings->method('getBool')
            ->willReturnCallback(fn(string $key, bool $default) => match ($key) {
                'briefing.llm.polish_enabled' => false,
                default => $default,
            });

        $this->writer = new TopicBriefingWriterService(
            $this->geminiCli,
            $this->claudeCli,
            $this->settings,
            $this->em,
            new NullLogger(),
        );

        $json = json_encode([
            'title' => 'Code Block Test',
            'summary_short' => 'Test.',
            'summary_long' => 'Longer test.',
            'why_it_matters' => 'Why.',
            'key_facts' => ['Fact'],
        ], \JSON_UNESCAPED_UNICODE);

        // Wrapped in markdown code block
        $this->geminiCli->method('execute')
            ->willReturn("```json\n{$json}\n```");

        $topic = $this->createTopic();

        $result = $this->writer->generate($topic, BriefingCadence::HOURLY, DateRange::lastHour());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertSame('Code Block Test', $result->getTitle());
    }

    #[Test]
    public function itSetsClaudePolishedWhenPolishSucceeds(): void
    {
        $this->mockPrQuery($this->createMockPrList(5));
        $this->em->expects($this->atLeastOnce())->method('persist');
        $this->em->expects($this->atLeastOnce())->method('flush');

        $geminiResponse = json_encode([
            'title' => 'Draft',
            'summary_short' => 'Draft scurt.',
            'summary_long' => 'Draft lung.',
            'why_it_matters' => 'Context.',
            'key_facts' => ['Fapt'],
        ], \JSON_UNESCAPED_UNICODE);

        $polishedResponse = json_encode([
            'title' => 'Polished Title',
            'summary_short' => 'Rezumat rafinat.',
            'summary_long' => 'Rezumat detaliat rafinat.',
            'why_it_matters' => 'Context rafinat.',
            'key_facts' => ['Fapt rafinat'],
        ], \JSON_UNESCAPED_UNICODE);

        $this->geminiCli->method('execute')->willReturn($geminiResponse);
        $this->claudeCli->method('chat')->willReturn($polishedResponse);

        $topic = $this->createTopic();

        $result = $this->writer->generate($topic, BriefingCadence::DAILY, DateRange::lastDay());

        $this->assertInstanceOf(TopicBriefing::class, $result);
        $this->assertTrue($result->isClaudePolished());
        $this->assertSame(BriefingStatus::POLISHED, $result->getStatus());
        $this->assertSame('Polished Title', $result->getTitle());
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
