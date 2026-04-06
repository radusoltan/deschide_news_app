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

        $this->logger->info('GenerateDailyBriefingHandler: generating {type} briefing', [
            'type' => $message->type,
            'date' => $date->format('Y-m-d'),
        ]);

        if ($message->type === 'morning') {
            $this->generateMorning($date);
        } else {
            $this->generateEvening($date);
        }
    }

    private function generateMorning(\DateTimeImmutable $date): void
    {
        $content = $this->briefingService->generateMorningBriefing($date);

        if ($content === null) {
            $this->logger->info('GenerateDailyBriefingHandler: no morning content generated');

            return;
        }

        $overnightStart = $date->modify('-1 day')->setTime(22, 0);
        $overnightEnd = $date->setTime(6, 0);
        $pressReleases = $this->briefingService->getOvernightPressReleases($overnightStart, $overnightEnd, 3.0);
        $articles = $this->briefingService->getArticlesForDate($overnightStart, $overnightEnd);

        $this->briefingService->saveMorningBriefing(
            $content,
            $date,
            \count($pressReleases) + \count($articles),
        );

        $this->logger->info('GenerateDailyBriefingHandler: morning briefing saved');
    }

    private function generateEvening(\DateTimeImmutable $date): void
    {
        $content = $this->briefingService->generateDailyBriefing($date);

        if ($content === null) {
            $this->logger->info('GenerateDailyBriefingHandler: no evening content generated');

            return;
        }

        $nextDay = $date->modify('+1 day');
        $articles = $this->briefingService->getArticlesForDate($date, $nextDay);

        $this->briefingService->saveBriefing($content, $date, \count($articles));

        $this->logger->info('GenerateDailyBriefingHandler: evening briefing saved');
    }
}
