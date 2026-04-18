<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Editorial\Monitor;

use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\EditorialAlignment;
use App\Message\Editorial\SignalIngestedMessage;
use App\Repository\Editorial\SourceSignalRepository;
use App\Repository\Editorial\VerifiedSourceRepository;
use App\Service\ContentHasher;
use App\Service\Editorial\Monitor\AbstractRssMonitor;
use App\Service\Editorial\Monitor\UrlNormalizer;
use App\Service\Scraping\RssFeedParser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;

/**
 * T53.5 — AbstractRssMonitor integration tests.
 *
 * Uses `MockHttpClient` + a canned RSS fixture and an in-test concrete
 * subclass to exercise the fetch → decode → parse → dedup → persist → dispatch
 * pipeline. The real DB is used (KernelTestCase) to validate the composite
 * unique hash index + FK to verified_sources.
 */
class AbstractRssMonitorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RssFeedParser $rssFeedParser;
    private ContentHasher $contentHasher;
    private UrlNormalizer $urlNormalizer;
    private VerifiedSourceRepository $verifiedSourceRepository;
    private SourceSignalRepository $sourceSignalRepository;

    /** @var list<Envelope> */
    private array $dispatchedEnvelopes = [];
    private MessageBusInterface $messageBus;

    /** @var list<int> */
    private array $sourceIdsToClean = [];

    /** @var list<int> */
    private array $verifiedSourceIdsToClean = [];

    /** @var list<int> */
    private array $signalIdsToClean = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get('doctrine')->getManager();
        $this->rssFeedParser = $container->get(RssFeedParser::class);
        $this->contentHasher = new ContentHasher();
        $this->urlNormalizer = new UrlNormalizer();
        $this->verifiedSourceRepository = $container->get(VerifiedSourceRepository::class);
        $this->sourceSignalRepository = $container->get(SourceSignalRepository::class);

        // In-memory bus that captures envelopes for assertion.
        $this->dispatchedEnvelopes = [];
        $capture = new class($this->dispatchedEnvelopes) implements \Symfony\Component\Messenger\Middleware\MiddlewareInterface {
            /** @param list<Envelope> $sink */
            public function __construct(private array &$sink)
            {
            }

            public function handle(Envelope $envelope, \Symfony\Component\Messenger\Middleware\StackInterface $stack): Envelope
            {
                $this->sink[] = $envelope;

                return $stack->next()->handle($envelope, $stack);
            }
        };
        // Capture-only bus — no HandleMessageMiddleware so we don't need to
        // stub a handler for SignalIngestedMessage.
        $this->messageBus = new MessageBus([$capture]);
    }

    protected function tearDown(): void
    {
        foreach ($this->signalIdsToClean as $id) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM source_signals WHERE id = :id',
                ['id' => $id],
            );
        }
        // Also clean anything persisted via the monitor itself (bound to our VS).
        foreach ($this->verifiedSourceIdsToClean as $vsId) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM source_signals WHERE verified_source_id = :id',
                ['id' => $vsId],
            );
            $this->em->getConnection()->executeStatement(
                'DELETE FROM verified_sources WHERE id = :id',
                ['id' => $vsId],
            );
        }
        foreach ($this->sourceIdsToClean as $id) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM sources WHERE id = :id',
                ['id' => $id],
            );
        }

        $this->em->close();
        parent::tearDown();
    }

    public function testHappyPathPersistsSignalsAndDispatchesMessages(): void
    {
        $xml = (string) file_get_contents(
            \dirname(__DIR__, 4) . '/Fixtures/rss/sample-rss-utf8.xml',
        );
        self::assertNotSame('', $xml);

        $httpClient = new MockHttpClient([new MockResponse($xml)]);
        $source = $this->seedSourceWithFeed('t53r5-wire-' . uniqid(), 'https://example.invalid/rss');
        $verifiedSource = $this->seedVerifiedSource(
            'vs-' . uniqid(),
            EditorialAlignment::WIRE_NEUTRAL,
            $source,
        );

        // Sanity: repository sees the persisted row.
        $reposFound = $this->verifiedSourceRepository->findEnabledByAlignments([EditorialAlignment::WIRE_NEUTRAL]);
        self::assertGreaterThanOrEqual(1, \count($reposFound), 'Persisted VerifiedSource visible to repository.');
        $matches = array_filter($reposFound, static fn ($r) => $r->getId() === $verifiedSource->getId());
        self::assertCount(1, $matches, 'Our specific seeded VS is in the result set.');

        $monitor = new InTestRssMonitor(
            alignments: [EditorialAlignment::WIRE_NEUTRAL],
            httpClient: $httpClient,
            rssFeedParser: $this->rssFeedParser,
            contentHasher: $this->contentHasher,
            urlNormalizer: $this->urlNormalizer,
            messageBus: $this->messageBus,
            entityManager: $this->em,
            verifiedSourceRepository: $this->verifiedSourceRepository,
            sourceSignalRepository: $this->sourceSignalRepository,
            logger: new NullLogger(),
        );

        $result = $monitor->fetchAndEmit();

        self::assertSame(3, $result->signalsEmitted, sprintf(
            'Three items parsed from fixture. Actual result: emitted=%d processed=%d skipped=%d errors=%s',
            $result->signalsEmitted,
            $result->sourcesProcessed,
            $result->sourcesSkipped,
            json_encode($result->errors),
        ));
        self::assertSame(1, $result->sourcesProcessed);
        self::assertSame([], $result->errors);

        $stored = $this->sourceSignalRepository->findRecentBySource($verifiedSource, 24);
        self::assertCount(3, $stored);

        // Canonical URL had utm_* stripped.
        $titles = array_map(static fn (SourceSignal $s): string => $s->getTitle(), $stored);
        self::assertContains('Prima știre din fixture', $titles);

        $first = array_values(array_filter(
            $stored,
            static fn (SourceSignal $s): bool => $s->getTitle() === 'Prima știre din fixture',
        ))[0];
        self::assertSame(
            'https://example.invalid/articol-1',
            $first->getCanonicalUrl(),
            'UrlNormalizer stripped utm_source + utm_campaign from articol-1 URL',
        );

        self::assertCount(3, $this->dispatchedEnvelopes, 'One SignalIngestedMessage dispatched per persisted signal.');
        foreach ($this->dispatchedEnvelopes as $envelope) {
            self::assertInstanceOf(SignalIngestedMessage::class, $envelope->getMessage());
        }
    }

    public function testDedupOnSecondRunEmitsZeroNewSignals(): void
    {
        $xml = (string) file_get_contents(
            \dirname(__DIR__, 4) . '/Fixtures/rss/sample-rss-utf8.xml',
        );

        $httpClient = new MockHttpClient([
            new MockResponse($xml),
            new MockResponse($xml),
        ]);
        $source = $this->seedSourceWithFeed('t53r5-dedup-' . uniqid(), 'https://example.invalid/rss');
        $this->seedVerifiedSource('vs-' . uniqid(), EditorialAlignment::WIRE_NEUTRAL, $source);

        $monitor = new InTestRssMonitor(
            alignments: [EditorialAlignment::WIRE_NEUTRAL],
            httpClient: $httpClient,
            rssFeedParser: $this->rssFeedParser,
            contentHasher: $this->contentHasher,
            urlNormalizer: $this->urlNormalizer,
            messageBus: $this->messageBus,
            entityManager: $this->em,
            verifiedSourceRepository: $this->verifiedSourceRepository,
            sourceSignalRepository: $this->sourceSignalRepository,
            logger: new NullLogger(),
        );

        $first = $monitor->fetchAndEmit();
        self::assertSame(3, $first->signalsEmitted);

        $second = $monitor->fetchAndEmit();
        self::assertSame(0, $second->signalsEmitted, 'Same feed re-ingested produces zero new signals.');
    }

    public function testSourceWithoutRssUrlIsSkipped(): void
    {
        $httpClient = new MockHttpClient([]);
        $source = $this->seedSourceWithFeed('t53r5-skip-' . uniqid(), rssUrl: null);
        $this->seedVerifiedSource('vs-' . uniqid(), EditorialAlignment::WIRE_NEUTRAL, $source);

        $monitor = new InTestRssMonitor(
            alignments: [EditorialAlignment::WIRE_NEUTRAL],
            httpClient: $httpClient,
            rssFeedParser: $this->rssFeedParser,
            contentHasher: $this->contentHasher,
            urlNormalizer: $this->urlNormalizer,
            messageBus: $this->messageBus,
            entityManager: $this->em,
            verifiedSourceRepository: $this->verifiedSourceRepository,
            sourceSignalRepository: $this->sourceSignalRepository,
            logger: new NullLogger(),
        );

        $result = $monitor->fetchAndEmit();

        self::assertSame(0, $result->signalsEmitted);
        self::assertSame(0, $result->sourcesProcessed);
        self::assertSame(1, $result->sourcesSkipped);
    }

    public function testDisabledSourceNotProcessed(): void
    {
        $httpClient = new MockHttpClient([]);
        $source = $this->seedSourceWithFeed('t53r5-disabled-' . uniqid(), 'https://example.invalid/rss');
        $vs = $this->seedVerifiedSource('vs-' . uniqid(), EditorialAlignment::WIRE_NEUTRAL, $source);
        $vs->setEnabled(false);
        $this->em->flush();

        $monitor = new InTestRssMonitor(
            alignments: [EditorialAlignment::WIRE_NEUTRAL],
            httpClient: $httpClient,
            rssFeedParser: $this->rssFeedParser,
            contentHasher: $this->contentHasher,
            urlNormalizer: $this->urlNormalizer,
            messageBus: $this->messageBus,
            entityManager: $this->em,
            verifiedSourceRepository: $this->verifiedSourceRepository,
            sourceSignalRepository: $this->sourceSignalRepository,
            logger: new NullLogger(),
        );

        $result = $monitor->fetchAndEmit();

        self::assertSame(0, $result->sourcesProcessed, 'Disabled VerifiedSource is not iterated.');
    }

    public function testHttpFailureIsLoggedAndOtherSourcesContinue(): void
    {
        $xmlOk = (string) file_get_contents(
            \dirname(__DIR__, 4) . '/Fixtures/rss/sample-rss-utf8.xml',
        );

        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 500]),
            new MockResponse($xmlOk),
        ]);

        $failingSource = $this->seedSourceWithFeed(
            't53r5-fail-' . uniqid(),
            'https://example.invalid/fail/rss',
        );
        $okSource = $this->seedSourceWithFeed(
            't53r5-ok-' . uniqid(),
            'https://example.invalid/ok/rss',
        );
        // Ensure deterministic iteration order by slug — both VS share WIRE_NEUTRAL.
        $this->seedVerifiedSource('vs-a-fail-' . uniqid(), EditorialAlignment::WIRE_NEUTRAL, $failingSource);
        $this->seedVerifiedSource('vs-b-ok-' . uniqid(), EditorialAlignment::WIRE_NEUTRAL, $okSource);

        $monitor = new InTestRssMonitor(
            alignments: [EditorialAlignment::WIRE_NEUTRAL],
            httpClient: $httpClient,
            rssFeedParser: $this->rssFeedParser,
            contentHasher: $this->contentHasher,
            urlNormalizer: $this->urlNormalizer,
            messageBus: $this->messageBus,
            entityManager: $this->em,
            verifiedSourceRepository: $this->verifiedSourceRepository,
            sourceSignalRepository: $this->sourceSignalRepository,
            logger: new NullLogger(),
        );

        $result = $monitor->fetchAndEmit();

        self::assertSame(
            3,
            $result->signalsEmitted,
            'Healthy source still emits 3 even though its sibling failed.',
        );
        self::assertSame(2, $result->sourcesProcessed);
    }

    public function testWindows1251FeedIsDecodedToUtf8(): void
    {
        // Build a Windows-1251 encoded RSS payload at test time. Cyrillic
        // "Тестовый заголовок" round-trips through mb_convert_encoding.
        $utf8Xml = '<?xml version="1.0" encoding="windows-1251"?>
<rss version="2.0"><channel>
<title>Legacy</title>
<link>https://legacy.invalid</link>
<description>test</description>
<item>
  <title>Тестовый заголовок</title>
  <link>https://legacy.invalid/a</link>
  <description>тест</description>
  <pubDate>Fri, 18 Apr 2026 09:00:00 +0300</pubDate>
</item>
</channel></rss>';
        $cp1251 = mb_convert_encoding($utf8Xml, 'Windows-1251', 'UTF-8');
        self::assertIsString($cp1251);

        $httpClient = new MockHttpClient([new MockResponse($cp1251)]);
        $source = $this->seedSourceWithFeed('t53r5-cp1251-' . uniqid(), 'https://legacy.invalid/rss');
        $this->seedVerifiedSource('vs-' . uniqid(), EditorialAlignment::INDEPENDENT_RU, $source, 'ru');

        $monitor = new InTestRssMonitor(
            alignments: [EditorialAlignment::INDEPENDENT_RU],
            httpClient: $httpClient,
            rssFeedParser: $this->rssFeedParser,
            contentHasher: $this->contentHasher,
            urlNormalizer: $this->urlNormalizer,
            messageBus: $this->messageBus,
            entityManager: $this->em,
            verifiedSourceRepository: $this->verifiedSourceRepository,
            sourceSignalRepository: $this->sourceSignalRepository,
            logger: new NullLogger(),
        );

        $result = $monitor->fetchAndEmit();

        self::assertSame(1, $result->signalsEmitted);

        $signals = $this->sourceSignalRepository->createQueryBuilder('s')
            ->orderBy('s.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();
        self::assertCount(1, $signals);
        /** @var SourceSignal $signal */
        $signal = $signals[0];
        self::assertSame('Тестовый заголовок', $signal->getTitle(), 'Cyrillic survives cp1251→utf8 decode.');
    }

    private function seedSourceWithFeed(string $name, ?string $rssUrl): Source
    {
        $source = new Source();
        $source->setName($name);
        if ($rssUrl !== null) {
            $source->setRssUrl($rssUrl);
        }
        $source->setCredibilityWeight(0.9);
        $source->setCountry('MD');
        $source->setFetchFrequencyMinutes(60);
        $source->setIsActive(true);

        $this->em->persist($source);
        $this->em->flush();

        $id = $source->getId();
        self::assertNotNull($id);
        $this->sourceIdsToClean[] = $id;

        return $source;
    }

    private function seedVerifiedSource(
        string $slug,
        EditorialAlignment $alignment,
        Source $source,
        ?string $language = 'ro',
    ): VerifiedSource {
        $vs = new VerifiedSource(
            slug: $slug,
            tier: 1,
            editorialAlignment: $alignment,
            trustScoreBaseline: '0.95',
            source: $source,
        );
        if ($language !== null) {
            $vs->setLanguage($language);
        }
        $this->em->persist($vs);
        $this->em->flush();

        $id = $vs->getId();
        self::assertNotNull($id);
        $this->verifiedSourceIdsToClean[] = $id;

        return $vs;
    }
}
