<?php

declare(strict_types=1);

namespace App\Service\Editorial\Monitor;

use App\Dto\Scraping\FeedItem;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Enum\EditorialAlignment;
use App\Message\Editorial\SignalIngestedMessage;
use App\Repository\Editorial\SourceSignalRepository;
use App\Repository\Editorial\VerifiedSourceRepository;
use App\Service\ContentHasher;
use App\Service\Scraping\RssFeedParser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Shared runtime for editorial-pipeline RSS monitors (Sprint 53 T53.5, ADR-020 D4).
 *
 * Concrete subclasses ({@see WireSourceMonitor}, {@see MediaRoSourceMonitor})
 * declare which {@see EditorialAlignment} values they service via
 * {@see getAlignmentFilters()}; the base class handles:
 *
 * 1. Selecting enabled {@see VerifiedSource} rows matching those alignments
 *    (only those with a linked Source that exposes an rss_url).
 * 2. Fetching the RSS payload with a configurable UA + timeout.
 * 3. Decoding Windows-1251 payloads to UTF-8 before handing off to the
 *    existing {@see RssFeedParser::parseXml()} pipeline — no RSS parsing
 *    is re-implemented here.
 * 4. Hashing title + canonical URL + summary via {@see ContentHasher} for
 *    per-source dedup against `source_signals.raw_content_hash`.
 * 5. Persisting new {@see SourceSignal} rows in batches and dispatching a
 *    {@see SignalIngestedMessage} for each (Sprint 53 handler logs only).
 *
 * Failure contract: any single source's fetch/parse error is logged and
 * swallowed; the run continues with remaining sources.
 */
abstract class AbstractRssMonitor
{
    private const BATCH_FLUSH_SIZE = 10;
    private const FEED_ITEM_LIMIT = 50;

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        protected readonly RssFeedParser $rssFeedParser,
        protected readonly ContentHasher $contentHasher,
        protected readonly UrlNormalizer $urlNormalizer,
        protected readonly MessageBusInterface $messageBus,
        protected readonly EntityManagerInterface $entityManager,
        protected readonly VerifiedSourceRepository $verifiedSourceRepository,
        protected readonly SourceSignalRepository $sourceSignalRepository,
        protected readonly LoggerInterface $logger,
        protected readonly string $userAgent = 'DeschideNewsBot/1.0',
        protected readonly int $timeoutSeconds = 10,
    ) {}

    /**
     * Alignments this monitor services. Sprint 53 monitors map 1:many across
     * alignments (e.g. WireSourceMonitor handles wire_neutral +
     * ukrainian_state + independent_ru + kremlin_aligned).
     *
     * @return list<EditorialAlignment>
     */
    abstract protected function getAlignmentFilters(): array;

    public function fetchAndEmit(): MonitorRunResult
    {
        $alignments = $this->getAlignmentFilters();
        if ($alignments === []) {
            return new MonitorRunResult(0, 0, 0, []);
        }

        $sources = $this->verifiedSourceRepository->findEnabledByAlignments($alignments);

        $sourcesProcessed = 0;
        $sourcesSkipped = 0;
        $signalsEmitted = 0;
        $errors = [];

        foreach ($sources as $source) {
            $rssUrl = $source->getRssUrl();
            if ($rssUrl === null || $rssUrl === '') {
                ++$sourcesSkipped;
                $this->logger->debug('AbstractRssMonitor: skipping source without rss_url', [
                    'verified_source_slug' => $source->getSlug(),
                ]);
                continue;
            }

            ++$sourcesProcessed;

            try {
                $emitted = $this->processSource($source, $rssUrl);
                $signalsEmitted += $emitted;
            } catch (\Throwable $e) {
                $errors[] = sprintf(
                    'source=%s: %s',
                    $source->getSlug(),
                    $e->getMessage(),
                );
                $this->logger->error('AbstractRssMonitor: source processing failed', [
                    'verified_source_slug' => $source->getSlug(),
                    'rss_url' => $rssUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new MonitorRunResult($signalsEmitted, $sourcesProcessed, $sourcesSkipped, $errors);
    }

    private function processSource(VerifiedSource $source, string $rssUrl): int
    {
        $xml = $this->fetchRssXml($rssUrl);
        if ($xml === null) {
            return 0;
        }

        $language = $source->getLanguage() ?? 'ro';
        $items = $this->rssFeedParser->parseXml($xml, $source->getName(), $language, self::FEED_ITEM_LIMIT);

        $emitted = 0;
        /** @var list<SourceSignal> $pendingBatch — signals persisted but not yet flushed */
        $pendingBatch = [];

        foreach ($items as $feedItem) {
            $signal = $this->buildSignalIfNew($source, $feedItem);
            if ($signal === null) {
                continue;
            }

            $this->entityManager->persist($signal);
            $pendingBatch[] = $signal;
            ++$emitted;

            if (\count($pendingBatch) >= self::BATCH_FLUSH_SIZE) {
                $this->flushAndDispatch($pendingBatch);
                $pendingBatch = [];
            }
        }

        if ($pendingBatch !== []) {
            $this->flushAndDispatch($pendingBatch);
        }

        return $emitted;
    }

    /**
     * Flush the current batch so each SourceSignal gets its id, then
     * dispatch a {@see SignalIngestedMessage} per id. Entities are not
     * detached — callers can continue to reference them if needed.
     *
     * @param list<SourceSignal> $batch
     */
    private function flushAndDispatch(array $batch): void
    {
        $this->entityManager->flush();

        foreach ($batch as $signal) {
            $id = $signal->getId();
            if ($id === null) {
                continue;
            }
            $this->messageBus->dispatch(new SignalIngestedMessage($id));
        }
    }

    private function fetchRssXml(string $rssUrl): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $rssUrl, [
                'headers' => ['User-Agent' => $this->userAgent],
                'timeout' => $this->timeoutSeconds,
            ]);
            $raw = $response->getContent();
        } catch (HttpClientExceptionInterface $e) {
            $this->logger->error('AbstractRssMonitor: HTTP fetch failed', [
                'rss_url' => $rssUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->decodeToUtf8($raw);
    }

    /**
     * Decode the raw RSS payload to UTF-8. SimpleXMLElement honours the XML
     * prolog encoding declaration, so the only manual conversion we need is
     * when the feed claims Windows-1251 (legacy RU outlets). Other encodings
     * fall through untouched.
     */
    private function decodeToUtf8(string $raw): string
    {
        if (\preg_match('/<\?xml[^>]*encoding=["\']([^"\']+)["\']/i', $raw, $matches) !== 1) {
            return $raw;
        }

        $declared = strtolower(trim($matches[1]));
        if ($declared === 'utf-8' || $declared === 'utf8') {
            return $raw;
        }

        if (\in_array($declared, ['windows-1251', 'cp1251', 'win1251'], true)) {
            $converted = mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
            // Rewrite the prolog so SimpleXML doesn't try to re-decode it.
            return (string) preg_replace(
                '/(<\?xml[^>]*encoding=["\'])[^"\']+(["\'])/i',
                '$1UTF-8$2',
                $converted,
                1,
            );
        }

        return $raw;
    }

    private function buildSignalIfNew(VerifiedSource $source, FeedItem $feedItem): ?SourceSignal
    {
        $sourceUrl = $feedItem->url;
        $canonical = $this->urlNormalizer->normalize($sourceUrl);

        $hash = $this->contentHasher->hash(
            $feedItem->title . "\n" . $canonical . "\n" . ($feedItem->description ?? ''),
        );

        if ($this->sourceSignalRepository->existsByHash($source, $hash)) {
            return null;
        }

        $signal = new SourceSignal(
            verifiedSource: $source,
            sourceUrl: $sourceUrl,
            title: $feedItem->title,
            rawContentHash: $hash,
        );
        $signal->setCanonicalUrl($canonical);
        $signal->setRawSummary($feedItem->description);
        $signal->setPublishedAt($feedItem->publishedAt);
        $signal->setRawPayload([
            'title' => $feedItem->title,
            'url' => $feedItem->url,
            'description' => $feedItem->description,
            'image_url' => $feedItem->imageUrl,
            'published_at' => $feedItem->publishedAt?->format(\DATE_ATOM),
            'source_name' => $feedItem->sourceName,
            'language' => $feedItem->language,
        ]);

        return $signal;
    }

}
