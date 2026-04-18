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
use App\Service\Editorial\Monitor\MediaRuSourceMonitor;
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
 * T54.5 — MediaRuSourceMonitor integration tests.
 *
 * Validates alignment filter wiring end-to-end: a MediaRuSourceMonitor run
 * must pick up INDEPENDENT_RU (Meduza) + KREMLIN_ALIGNED (Interfax-like)
 * and NOT pick up wire_neutral or md_* sources.
 */
class MediaRuSourceMonitorTest extends KernelTestCase
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

    public function testMediaRuMonitorFetchesRuAlignedSourcesAndSkipsOthers(): void
    {
        $meduzaXml = (string) file_get_contents(\dirname(__DIR__, 4) . '/Fixtures/rss/meduza-sample.xml');
        $reutersXml = (string) file_get_contents(\dirname(__DIR__, 4) . '/Fixtures/rss/reuters-sample.xml');

        // Slug prefixes force alphabetical order so the MockHttpClient
        // queue matches the order the repository yields (tier ASC, slug ASC).
        // kremlin_aligned (b-) will use reuters-sample as its payload here
        // purely to exercise the alignment filter; content itself is not asserted.
        $httpClient = new MockHttpClient([
            new MockResponse($meduzaXml),
            new MockResponse($reutersXml),
        ]);

        $meduzaSource = $this->seedSource('Meduza-' . uniqid(), 'https://meduza.io/rss/all');
        $interfaxSource = $this->seedSource('Interfax-' . uniqid(), 'https://www.interfax.com/rss.asp');
        $zdgSource = $this->seedSource('ZDG-' . uniqid(), 'https://www.zdg.md/feed/');

        $meduzaVs = $this->seedVerifiedSource('a-indru-meduza', EditorialAlignment::INDEPENDENT_RU, $meduzaSource);
        $interfaxVs = $this->seedVerifiedSource('b-krml-interfax', EditorialAlignment::KREMLIN_ALIGNED, $interfaxSource);
        // md_investigative — must NOT be picked up by MediaRuSourceMonitor.
        $this->seedVerifiedSource('c-md-zdg', EditorialAlignment::MD_INVESTIGATIVE, $zdgSource);

        $monitor = new MediaRuSourceMonitor(
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

        self::assertSame(
            2,
            $result->sourcesProcessed,
            'INDEPENDENT_RU + KREMLIN_ALIGNED VS processed, md_* not iterated.',
        );
        self::assertSame([], $result->errors);

        $meduzaStored = $this->sourceSignalRepository->findRecentBySource($meduzaVs, 24);
        self::assertNotEmpty($meduzaStored, 'Meduza signals should be persisted.');

        foreach ($meduzaStored as $signal) {
            self::assertInstanceOf(SourceSignal::class, $signal);
            self::assertSame($meduzaVs->getId(), $signal->getVerifiedSource()->getId());
        }
    }

    public function testAlignmentFiltersDeclaresRuSet(): void
    {
        $monitor = new MediaRuSourceMonitor(
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
        $source->setCredibilityWeight(0.5);
        $source->setCountry('RU');
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
            trustScoreBaseline: '0.50',
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
