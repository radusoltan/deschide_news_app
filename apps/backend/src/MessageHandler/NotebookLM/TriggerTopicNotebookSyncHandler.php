<?php

declare(strict_types=1);

namespace App\MessageHandler\NotebookLM;

use App\Message\NotebookLM\TriggerTopicNotebookSyncMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Process\Process;

#[AsMessageHandler]
final class TriggerTopicNotebookSyncHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(TriggerTopicNotebookSyncMessage $message): void
    {
        $this->logger->info('TopicNotebookSync: scheduler trigger fired');

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

        $this->logger->info('TopicNotebookSync: CLI command completed', [
            'output' => $process->getOutput(),
        ]);
    }
}
