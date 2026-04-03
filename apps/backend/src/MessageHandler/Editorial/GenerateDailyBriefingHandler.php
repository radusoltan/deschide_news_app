<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\GenerateDailyBriefingMessage;
use App\Service\Editorial\DailyBriefingService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateDailyBriefingHandler
{
    public function __construct(
        private DailyBriefingService $briefingService,
        private LoggerInterface $logger,
        #[Autowire('%env(default::VAULT_PATH)%')] private string $vaultPath = '',
    ) {}

    public function __invoke(GenerateDailyBriefingMessage $message): void
    {
        if ($this->vaultPath === '') {
            $this->logger->warning('GenerateDailyBriefingHandler: VAULT_PATH not configured, skipping');

            return;
        }

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

        $this->briefingService->saveBriefing($content, $date, $this->vaultPath);

        $this->logger->info('GenerateDailyBriefingHandler: briefing saved', [
            'date' => $date->format('Y-m-d'),
        ]);
    }
}
