<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Editorial\Monitor;

use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\EditorialAlignment;
use App\Repository\Editorial\SourceSignalRepository;
use App\Repository\Editorial\VerifiedSourceRepository;
use App\Service\ContentHasher;
use App\Service\Editorial\Monitor\MediaRoSourceMonitor;
use App\Service\Editorial\Monitor\UrlNormalizer;
use App\Service\Scraping\RssFeedParser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

/**
 * T53.7 — MediaRoSourceMonitor integration tests.
 *
 * Validates alignment filter wiring end-to-end: a MediaRoSourceMonitor run
 * must pick up ZDG (md_investigative) + Digi24 (ro_mainstream) and NOT pick
 * up wire sources like Reuters even when they are enabled.
 */
class MediaRoSourceMonitorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RssFeedParser $rssFeedParser;
    private ContentHasher $contentHasher;
    private UrlNormalizer $urlNormalizer;
    private VerifiedSourceRepository $verifiedSourceRepository;
    private SourceSignalRepository $sourceSignalRepository;

    /** @var list<Envelope> */
    private array $dispatchedEnvelopes = [];
    private MessageBus $messageBus;

    /** @var list<int> */
    private array $sourceIdsToClean = [];

    /** @var list<int> */
    private array $verifiedSourceIdsToClean = [];

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

        $this->dispatchedEnvelopes = [];
        $capture = new class($this->dispatchedEnvelopes) implements MiddlewareInterface {
            /** @param list<Envelope> $sink */
            public function __construct(private array &$sink)
            {
            }

            public function handle(Envelope $envelope, StackInterface $stack): Envelope
            {
                $this->sink[] = $envelope;

                return $stack->next()->handle($envelope, $stack);
            }
        };
        $this->messageBus = new MessageBus([$capture]);
    }

    protected function tearDown(): void
    {
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

    public function testMediaRoMonitorFetchesZdgAndDigi24ButSkipsWire(): void
    {
        $zdgXml = (string) file_get_contents(\dirname(__DIR__, 4) . '/Fixtures/rss/zdg-sample.xml');
        $digi24Xml = (string) file_get_contents(\dirname(__DIR__, 4) . '/Fixtures/rss/digi24-sample.xml');

        // Repository orders tier ASC, slug ASC. Both seeded at tier=1.
        // Slug prefixes force alphabetical order: 'a-' < 'b-'.
        $httpClient = new MockHttpClient([
            new MockResponse($digi24Xml),
            new MockResponse($zdgXml),
        ]);

        $zdgSource = $this->seedSource('ZDG-' . uniqid(), 'https://www.zdg.md/feed/');
        $digi24Source = $this->seedSource('Digi24-' . uniqid(), 'https://www.digi24.ro/rss');
        $reutersSource = $this->seedSource('Reuters-' . uniqid(), 'https://www.reuters.com/rss');

        $digi24Vs = $this->seedVerifiedSource('a-ro-digi24', EditorialAlignment::RO_MAINSTREAM, $digi24Source);
        $zdgVs = $this->seedVerifiedSource('b-md-zdg', EditorialAlignment::MD_INVESTIGATIVE, $zdgSource);
        // WIRE_NEUTRAL — must NOT be picked up by MediaRoSourceMonitor.
        $this->seedVerifiedSource('c-wire-reuters', EditorialAlignment::WIRE_NEUTRAL, $reutersSource);

        $monitor = new MediaRoSourceMonitor(
            $httpClient,
            $this->rssFeedParser,
            $this->contentHasher,
            $this->urlNormalizer,
            $this->messageBus,
            $this->em,
            $this->verifiedSourceRepository,
            $this->sourceSignalRepository,
            new NullLogger(),
        );

        $result = $monitor->fetchAndEmit();

        self::assertSame(2, $result->sourcesProcessed, 'MD + RO VS processed, wire VS not iterated.');
        self::assertSame(4, $result->signalsEmitted, '2 Digi24 items + 2 ZDG items.');
        self::assertSame([], $result->errors);

        $digi24Stored = $this->sourceSignalRepository->findRecentBySource($digi24Vs, 24);
        self::assertCount(2, $digi24Stored);

        $zdgStored = $this->sourceSignalRepository->findRecentBySource($zdgVs, 24);
        self::assertCount(2, $zdgStored);

        // Romanian diacritics and investigative-title shape survive.
        $zdgTitles = array_map(static fn (SourceSignal $s): string => $s->getTitle(), $zdgStored);
        self::assertContains(
            'Investigație: achiziții publice netransparente la Ministerul Infrastructurii',
            $zdgTitles,
        );
    }

    public function testAlignmentFiltersDeclaresMediaRoSet(): void
    {
        $monitor = new MediaRoSourceMonitor(
            new MockHttpClient([]),
            $this->rssFeedParser,
            $this->contentHasher,
            $this->urlNormalizer,
            $this->messageBus,
            $this->em,
            $this->verifiedSourceRepository,
            $this->sourceSignalRepository,
            new NullLogger(),
        );

        $reflection = new \ReflectionMethod($monitor, 'getAlignmentFilters');
        $filters = $reflection->invoke($monitor);

        self::assertEqualsCanonicalizing(
            [
                EditorialAlignment::MD_INVESTIGATIVE,
                EditorialAlignment::MD_INDEPENDENT_PRO_EU,
                EditorialAlignment::MD_GOVERNMENT,
                EditorialAlignment::RO_MAINSTREAM,
            ],
            $filters,
        );
    }

    private function seedSource(string $name, string $rssUrl): Source
    {
        $source = new Source();
        $source->setName($name);
        $source->setRssUrl($rssUrl);
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
    ): VerifiedSource {
        $fullSlug = $slug . '-' . uniqid();
        $vs = new VerifiedSource(
            slug: $fullSlug,
            tier: 1,
            editorialAlignment: $alignment,
            trustScoreBaseline: '0.85',
            source: $source,
        );
        $this->em->persist($vs);
        $this->em->flush();

        $id = $vs->getId();
        self::assertNotNull($id);
        $this->verifiedSourceIdsToClean[] = $id;

        return $vs;
    }
}
