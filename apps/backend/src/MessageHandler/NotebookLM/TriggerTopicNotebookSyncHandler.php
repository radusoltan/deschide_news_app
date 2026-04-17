<?php

declare(strict_types=1);

namespace App\MessageHandler\NotebookLM;

use App\Message\NotebookLM\TriggerTopicNotebookSyncMessage;
use App\Repository\AppSettingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsMessageHandler]
final class TriggerTopicNotebookSyncHandler
{
    private const LAST_RUN_KEY = 'notebooklm.sync.last_run';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly AppSettingRepository $settings,
        private readonly CacheInterface $cache,
    ) {}

    public function __invoke(TriggerTopicNotebookSyncMessage $message): void
    {
        $this->logger->info('TopicNotebookSync: scheduler trigger fired');

        // Respect the AppSettings interval window — avoid running more often than configured.
        $intervalHours = $this->settings->getInt('notebooklm.sync.interval_hours', 6);
        $lastRun = (int) $this->cache->get(
            self::LAST_RUN_KEY,
            static function (ItemInterface $item): int {
                $item->expiresAfter(86400 * 7);

                return 0;
            },
        );

        if ($lastRun > 0 && (time() - $lastRun) < $intervalHours * 3600) {
            $this->logger->info('TopicNotebookSync: skipped — within interval window', [
                'intervalHours' => $intervalHours,
                'secondsSinceLastRun' => time() - $lastRun,
            ]);

            return;
        }

        $process = new Process([
            'symfony', 'console', 'app:topic:sync-notebooks', '--no-interaction',
        ]);
        $process->setTimeout(1800); // 30 min ceiling
        $process->run();

        if (!$process->isSuccessful()) {
            $this->logger->error('TopicNotebookSync: CLI command failed', [
                'exitCode' => $process->getExitCode(),
                'error' => $process->getErrorOutput(),
            ]);

            return;
        }

        // Record successful run timestamp for the interval check.
        $this->cache->delete(self::LAST_RUN_KEY);
        $this->cache->get(self::LAST_RUN_KEY, static function (ItemInterface $item): int {
            $item->expiresAfter(86400 * 7);

            return time();
        });

        $this->logger->info('TopicNotebookSync: CLI command completed', [
            'output' => $process->getOutput(),
        ]);
    }
}
