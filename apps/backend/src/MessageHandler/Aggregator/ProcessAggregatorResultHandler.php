<?php

declare(strict_types=1);

namespace App\MessageHandler\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Enum\DeduplicationResult;
use App\Message\Aggregator\ProcessAggregatorResultMessage;
use App\Repository\PressReleaseRepository;
use App\Repository\SourceRepository;
use App\Message\Scraping\ScrapeFullContentMessage;
use App\Message\Topic\DetectTopicsForPressReleaseMessage;
use App\Service\Aggregator\AggregatorStatsCollector;
use App\Service\Aggregator\GoogleNewsUrlResolver;
use App\Service\Aggregator\PressReleaseAggregatorFactory;
use App\Service\Aggregator\SemanticDeduplicatorService;
use App\Service\Translation\AggregatorTranslationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ProcessAggregatorResultHandler
{
    public function __construct(
        private SemanticDeduplicatorService $deduplicator,
        private PressReleaseAggregatorFactory $factory,
        private AggregatorTranslationService $translationService,
        private GoogleNewsUrlResolver $urlResolver,
        private PressReleaseRepository $pressReleaseRepository,
        private SourceRepository $sourceRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private AggregatorStatsCollector $statsCollector,
        private MessageBusInterface $messageBus,
    ) {}

    public function __invoke(ProcessAggregatorResultMessage $message): void
    {
        $this->logger->info('ProcessAggregatorResultHandler: processing', [
            'title' => mb_substr($message->title, 0, 80),
            'source' => $message->sourceName,
            'language' => $message->sourceLanguage,
        ]);

        // 0. Source URL dedup — skip if already imported from this URL
        if ($message->sourceUrl !== null) {
            $existing = $this->pressReleaseRepository->findBySourceUrl($message->sourceUrl);
            if ($existing !== null) {
                $this->logger->debug('ProcessAggregatorResultHandler: duplicate source_url, skipping', [
                    'sourceUrl' => $message->sourceUrl,
                    'existingId' => $existing->getId(),
                ]);

                return;
            }
        }

        // Check deduplication
        $dedupResult = $this->deduplicator->evaluate($message->title, $message->rawContent);

        if ($dedupResult === DeduplicationResult::DUPLICATE) {
            $this->logger->info('ProcessAggregatorResultHandler: skipped duplicate', [
                'title' => mb_substr($message->title, 0, 80),
            ]);

            return;
        }

        // Reconstitute AggregatorResult
        $aggregatorResult = new AggregatorResult(
            title: $message->title,
            summary: $message->summary,
            sourceUrl: $message->sourceUrl,
            sourceLanguage: $message->sourceLanguage,
            sourceName: $message->sourceName,
            publishedAt: new \DateTimeImmutable($message->publishedAt),
            rawContent: $message->rawContent,
            keywords: $message->keywords,
            aggregatorSourceType: AggregatorSourceType::tryFrom($message->aggregatorSourceType),
            sourcePublisherDomain: $message->sourcePublisherDomain,
        );

        // Create PressRelease
        $pressRelease = $this->factory->createFromAggregatorResult($aggregatorResult);

        // Resolve Google News redirect URLs to real article URLs
        if (str_contains($message->sourceName, 'Google News') && $pressRelease->getSourceUrl() !== null) {
            $resolvedUrl = $this->urlResolver->resolveUrl($pressRelease->getSourceUrl());
            if ($resolvedUrl !== null) {
                $pressRelease->setSourceUrl($resolvedUrl);
            }
        }

        // Translate to Romanian if not already in Romanian
        if ($message->sourceLanguage !== 'ro') {
            $pressRelease->setOriginalTitle($pressRelease->getTitle());
            $pressRelease->setOriginalContent($pressRelease->getContent());
            $this->translationService->translateToRomanian($pressRelease);
        }

        // Set detected_language from source language
        $pressRelease->setDetectedLanguage($message->sourceLanguage);

        // Link to Source entity via domain matching
        $hostname = $pressRelease->getSourceHostname();
        if ($hostname !== null) {
            $source = $this->sourceRepository->findByDomain($hostname);
            if ($source !== null) {
                $pressRelease->setSource($source);
            }
        }

        try {
            $this->em->persist($pressRelease);
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            $this->logger->info('ProcessAggregatorResultHandler: duplicate detected on flush, skipping', [
                'sourceUrl' => $message->sourceUrl,
                'error' => $e->getMessage(),
            ]);
            $this->em->clear();

            return;
        }

        $this->statsCollector->invalidateCache();

        // Dispatch async topic detection (ADR-015)
        if ($pressRelease->getId() !== null) {
            $this->messageBus->dispatch(new DetectTopicsForPressReleaseMessage($pressRelease->getId()));
        }

        // Auto-enrich thin content from credible sources
        $sourceCredibility = $pressRelease->getSource()?->getCredibilityWeight() ?? 0.0;
        $sourceUrl = $pressRelease->getSourceUrl();
        if (
            $sourceCredibility >= 0.70
            && $pressRelease->getContentLength() < 500
            && $sourceUrl !== null
            && filter_var($sourceUrl, \FILTER_VALIDATE_URL) !== false
        ) {
            $this->messageBus->dispatch(
                new ScrapeFullContentMessage($pressRelease->getId(), $sourceUrl)
            );
            $this->logger->debug('ProcessAggregatorResultHandler: dispatched content enrichment', [
                'id' => $pressRelease->getId(),
                'credibility' => $sourceCredibility,
                'contentLength' => $pressRelease->getContentLength(),
            ]);
        }

        $this->logger->info('ProcessAggregatorResultHandler: saved PressRelease #{id}', [
            'id' => $pressRelease->getId(),
            'title' => mb_substr($pressRelease->getTitle(), 0, 80),
            'dedupStatus' => $dedupResult->value,
        ]);
    }
}
