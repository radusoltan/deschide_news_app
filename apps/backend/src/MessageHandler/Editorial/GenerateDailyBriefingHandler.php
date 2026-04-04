<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\GenerateDailyBriefingMessage;
use App\Service\Editorial\DailyBriefingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateDailyBriefingHandler
{
    public function __construct(
        private DailyBriefingService $briefingService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(GenerateDailyBriefingMessage $message): void
    {
        $date = $message->date !== null
            ? new \DateTimeImmutable($message->date)
            : new \DateTimeImmutable('today');

        $this->logger->info('GenerateDailyBriefingHandler: generating briefing', [
            'date' => $date->format('Y-m-d'),
        ]);

        $content = $this->briefingService->generateDailyBriefing($date);

        if ($content === null) {
            $this->logger->info('GenerateDailyBriefingHandler: no content generated (no articles?)');

            return;
        }

        $nextDay = $date->modify('+1 day');
        $articles = $this->briefingService->getArticlesForDate($date, $nextDay);

        $this->briefingService->saveBriefing($content, $date, \count($articles));

        $this->logger->info('GenerateDailyBriefingHandler: briefing saved', [
            'date' => $date->format('Y-m-d'),
        ]);
    }
}
