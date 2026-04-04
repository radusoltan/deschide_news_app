<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\GenerateWeeklySummaryMessage;
use App\Service\Editorial\WeeklySummaryService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateWeeklySummaryHandler
{
    public function __construct(
        private WeeklySummaryService $summaryService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(GenerateWeeklySummaryMessage $message): void
    {
        $weekEnd = $message->weekEnd !== null
            ? new \DateTimeImmutable($message->weekEnd)
            : new \DateTimeImmutable('Sunday this week');

        $this->logger->info('GenerateWeeklySummaryHandler: generating weekly summary', [
            'weekEnd' => $weekEnd->format('Y-m-d'),
        ]);

        $content = $this->summaryService->generateWeeklySummary($weekEnd);

        if ($content === null) {
            $this->logger->info('GenerateWeeklySummaryHandler: no content generated');

            return;
        }

        $weekStart = $weekEnd->modify('-6 days');
        $articles = $this->summaryService->getArticlesForPeriod($weekStart, $weekEnd);

        $this->summaryService->saveSummary($content, $weekStart, $weekEnd, \count($articles));

        $this->logger->info('GenerateWeeklySummaryHandler: summary saved', [
            'week' => $weekEnd->format('Y-\WW'),
        ]);
    }
}
