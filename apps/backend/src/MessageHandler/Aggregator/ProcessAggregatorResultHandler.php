<?php

declare(strict_types=1);

namespace App\MessageHandler\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Enum\DeduplicationResult;
use App\Message\Aggregator\ProcessAggregatorResultMessage;
use App\Service\Aggregator\PressReleaseAggregatorFactory;
use App\Service\Aggregator\SemanticDeduplicatorService;
use App\Service\Translation\AggregatorTranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessAggregatorResultHandler
{
    public function __construct(
        private SemanticDeduplicatorService $deduplicator,
        private PressReleaseAggregatorFactory $factory,
        private AggregatorTranslationService $translationService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ProcessAggregatorResultMessage $message): void
    {
        $this->logger->info('ProcessAggregatorResultHandler: processing', [
            'title' => mb_substr($message->title, 0, 80),
            'source' => $message->sourceName,
            'language' => $message->sourceLanguage,
        ]);

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
        );

        // Create PressRelease
        $pressRelease = $this->factory->createFromAggregatorResult($aggregatorResult);

        // Translate to Romanian if not already in Romanian
        if ($message->sourceLanguage !== 'ro') {
            $this->translationService->translateToRomanian($pressRelease);
        }

        $this->em->persist($pressRelease);
        $this->em->flush();

        $this->logger->info('ProcessAggregatorResultHandler: saved PressRelease #{id}', [
            'id' => $pressRelease->getId(),
            'title' => mb_substr($pressRelease->getTitle(), 0, 80),
            'dedupStatus' => $dedupResult->value,
        ]);
    }
}
