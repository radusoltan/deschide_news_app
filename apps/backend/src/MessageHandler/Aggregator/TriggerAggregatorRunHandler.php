<?php

declare(strict_types=1);

namespace App\MessageHandler\Aggregator;

use App\Entity\AggregatorRun;
use App\Message\Aggregator\ProcessAggregatorResultMessage;
use App\Message\Aggregator\TriggerAggregatorRunMessage;
use App\Service\Aggregator\AggregatorInterface;
use App\Service\Aggregator\AggregatorStatsCollector;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class TriggerAggregatorRunHandler
{
    /**
     * @param iterable<AggregatorInterface> $aggregators
     */
    public function __construct(
        #[AutowireIterator('app.aggregator')]
        private readonly iterable $aggregators,
        private readonly MessageBusInterface $messageBus,
        private readonly AggregatorStatsCollector $statsCollector,
        private readonly LoggerInterface $logger,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function __invoke(TriggerAggregatorRunMessage $message): void
    {
        $this->logger->info('Manual aggregator run triggered', [
            'source' => $message->source ?? 'all',
            'triggeredBy' => $message->triggeredBy,
        ]);

        $run = new AggregatorRun();
        $run->setSource($message->source ?? 'all');
        $run->setTriggeredBy($message->triggeredBy);
        $this->entityManager->persist($run);
        $this->entityManager->flush();

        try {
            $totalDispatched = 0;

            foreach ($this->aggregators as $aggregator) {
                if ($message->source !== null && $aggregator->getSourceType()->value !== $message->source) {
                    continue;
                }

                try {
                    $results = $aggregator->fetch();
                } catch (\Throwable $e) {
                    $this->logger->error('Aggregator fetch failed', [
                        'source' => $aggregator->getSourceType()->value,
                        'error' => $e->getMessage(),
                    ]);
                    $run->addErrorDetail(sprintf('%s: %s', $aggregator->getSourceType()->value, $e->getMessage()));
                    $run->incrementErrorsCount();
                    continue;
                }

                $run->incrementArticlesFound(\count($results));

                foreach ($results as $result) {
                    $this->messageBus->dispatch(new ProcessAggregatorResultMessage(
                        title: $result->title,
                        summary: $result->summary,
                        sourceUrl: $result->sourceUrl,
                        sourceLanguage: $result->sourceLanguage,
                        sourceName: $result->sourceName,
                        publishedAt: $result->publishedAt->format('c'),
                        rawContent: $result->rawContent,
                        keywords: $result->keywords,
                        aggregatorSourceType: $result->aggregatorSourceType?->value ?? '',
                    ));
                    ++$totalDispatched;
                }
            }

            $this->statsCollector->invalidateCache();

            $run->markCompleted();
            $this->entityManager->flush();

            $this->logger->info('Manual aggregator run completed', [
                'runId' => $run->getId()->toRfc4122(),
                'totalResults' => $run->getArticlesFound(),
                'totalDispatched' => $totalDispatched,
                'triggeredBy' => $message->triggeredBy,
            ]);
        } catch (\Throwable $e) {
            $run->markFailed($e->getMessage());
            $this->entityManager->flush();

            $this->logger->error('Manual aggregator run failed', [
                'runId' => $run->getId()->toRfc4122(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
