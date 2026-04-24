<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\Scraping\PythonScrapeMessage;
use App\Service\Scraping\PythonScraperService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedules Python scraper runs based on sources.yaml frequency config.
 *
 * To run: symfony console messenger:consume scheduler_python_scraper -vv
 */
#[AsSchedule('python_scraper')]
class PythonScraperScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly PythonScraperService $scraperService,
        private readonly LoggerInterface $logger,
    ) {}

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();

        $sources = $this->scraperService->getConfiguredSources();

        foreach ($sources as $key => $cfg) {
            $frequency = $cfg['frequency'];

            $schedule->add(RecurringMessage::every(
                $frequency . ' minutes',
                new PythonScrapeMessage(
                    sourceName: $key,
                    sourceUrl: $cfg['url'],
                    sourceType: $cfg['type'],
                ),
            ));

            $this->logger->debug('PythonScraperScheduler: registered {source} every {freq}m', [
                'source' => $key,
                'freq' => $frequency,
            ]);
        }

        return $schedule;
    }
}
