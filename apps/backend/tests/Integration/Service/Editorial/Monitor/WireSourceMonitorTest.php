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

    public function testWireMonitorFetchesReutersButSkipsOtherAlignments(): void
    {
        // Sprint 54 T54.11 narrowed WireSourceMonitor to WIRE_NEUTRAL +
        // UKRAINIAN_STATE. INDEPENDENT_RU and KREMLIN_ALIGNED moved
        // single-owner to MediaRuSourceMonitor; MD_* belong to
        // MediaRoSourceMonitor. This test exercises that narrowing by
        // seeding one source per alignment bucket and asserting the wire
        // monitor only touches the WIRE_NEUTRAL one.
        $reutersXml = (string) file_get_contents(\dirname(__DIR__, 4) . '/Fixtures/rss/reuters-sample.xml');

        $httpClient = new MockHttpClient([
            new MockResponse($reutersXml),
        ]);

        $reutersSource = $this->seedSource('Reuters-' . uniqid(), 'https://www.reuters.com/rss');
        $meduzaSource = $this->seedSource('Meduza-' . uniqid(), 'https://meduza.io/rss');
        $zdgSource = $this->seedSource('ZDG-' . uniqid(), 'https://www.zdg.md/feed/');

        $reutersVs = $this->seedVerifiedSource('a-wire-reuters', EditorialAlignment::WIRE_NEUTRAL, $reutersSource);
        // INDEPENDENT_RU — no longer picked up by WireSourceMonitor post T54.11.
        $this->seedVerifiedSource('b-indep-meduza', EditorialAlignment::INDEPENDENT_RU, $meduzaSource);
        // MD_INVESTIGATIVE — must NOT be picked up by WireSourceMonitor.
        $this->seedVerifiedSource('c-md-zdg', EditorialAlignment::MD_INVESTIGATIVE, $zdgSource);

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

        self::assertSame(1, $result->sourcesProcessed, 'Only the WIRE_NEUTRAL VS is iterated after T54.11 narrowing.');
        self::assertSame(3, $result->signalsEmitted, '3 Reuters items.');
        self::assertSame([], $result->errors);

        $reutersStored = $this->sourceSignalRepository->findRecentBySource($reutersVs, 24);
        self::assertCount(3, $reutersStored);
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
            ],
            $filters,
            'WireSourceMonitor narrowed in Sprint 54 T54.11 — INDEPENDENT_RU and '
            . 'KREMLIN_ALIGNED are now single-owned by MediaRuSourceMonitor.',
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
