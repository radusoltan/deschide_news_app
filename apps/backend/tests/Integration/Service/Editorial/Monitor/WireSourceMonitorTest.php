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
use App\Service\Editorial\Monitor\UrlNormalizer;
use App\Service\Editorial\Monitor\WireSourceMonitor;
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
 * T53.6 — WireSourceMonitor integration tests.
 *
 * Validates the alignment filter wiring end-to-end: a WireSourceMonitor run
 * must pick up Reuters (wire_neutral) + Meduza (independent_ru) sources and
 * NOT pick up MD/RO outlets even when they exist and are enabled.
 */
class WireSourceMonitorTest extends KernelTestCase
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

    public function testWireMonitorFetchesReutersAndMeduzaButSkipsMdOutlets(): void
    {
        $reutersXml = (string) file_get_contents(\dirname(__DIR__, 4) . '/Fixtures/rss/reuters-sample.xml');
        $meduzaXml = (string) file_get_contents(\dirname(__DIR__, 4) . '/Fixtures/rss/meduza-sample.xml');

        // MockHttpClient returns responses in order of HTTP request. Since
        // the monitor orders sources by tier ASC then slug ASC (see
        // VerifiedSourceRepository), queue in that order. With tier=1 on both:
        // slug-ASC → "vs-meduza-..." (m) < "vs-reuters-..." (r). We use
        // explicit slug prefixes to make ordering deterministic.
        $httpClient = new MockHttpClient([
            new MockResponse($meduzaXml),
            new MockResponse($reutersXml),
        ]);

        $reutersSource = $this->seedSource('Reuters-' . uniqid(), 'https://www.reuters.com/rss');
        $meduzaSource = $this->seedSource('Meduza-' . uniqid(), 'https://meduza.io/rss');
        $zdgSource = $this->seedSource('ZDG-' . uniqid(), 'https://www.zdg.md/feed/');

        $reutersVs = $this->seedVerifiedSource('a-wire-reuters', EditorialAlignment::WIRE_NEUTRAL, $reutersSource);
        $meduzaVs = $this->seedVerifiedSource('a-indep-meduza', EditorialAlignment::INDEPENDENT_RU, $meduzaSource);
        // MD_INVESTIGATIVE — must NOT be picked up by WireSourceMonitor.
        $this->seedVerifiedSource('b-md-zdg', EditorialAlignment::MD_INVESTIGATIVE, $zdgSource);

        $monitor = new WireSourceMonitor(
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

        self::assertSame(2, $result->sourcesProcessed, 'Two wire-family VS processed, MD VS not iterated.');
        self::assertSame(5, $result->signalsEmitted, '3 Reuters items + 2 Meduza items.');
        self::assertSame([], $result->errors);

        $reutersStored = $this->sourceSignalRepository->findRecentBySource($reutersVs, 24);
        self::assertCount(3, $reutersStored);

        $meduzaStored = $this->sourceSignalRepository->findRecentBySource($meduzaVs, 24);
        self::assertCount(2, $meduzaStored);

        // Cyrillic round-trip through the pipeline.
        $titles = array_map(static fn (SourceSignal $s): string => $s->getTitle(), $meduzaStored);
        self::assertContains('Новое расследование о коррупции в регионах', $titles);
    }

    public function testAlignmentFiltersDeclaresWireLikeSet(): void
    {
        $monitor = new WireSourceMonitor(
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
                EditorialAlignment::WIRE_NEUTRAL,
                EditorialAlignment::UKRAINIAN_STATE,
                EditorialAlignment::INDEPENDENT_RU,
                EditorialAlignment::KREMLIN_ALIGNED,
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
        $source->setCountry('XX');
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
        // Use a unique slug suffix so parallel test seeds don't collide.
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
