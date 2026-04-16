<?php

declare(strict_types=1);

namespace App\Service\NotebookLM;

use App\Entity\Topic;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final class NotebookLMService
{
    private const DEFAULT_TIMEOUT = 120;
    private const AUDIO_TIMEOUT = 300;

    public function __construct(
        private readonly bool $enabled,
        private readonly string $cliPath,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Check if NotebookLM CLI is available and authenticated.
     */
    public function isAvailable(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $process = $this->run(['auth', 'check', '--test']);

        return $process !== null && $process->isSuccessful();
    }

    /**
     * Add a URL source to a notebook.
     */
    public function addSource(string $notebookId, string $url): bool
    {
        if (!$this->enabled || $notebookId === '') {
            return false;
        }

        // Select notebook, then add source
        $select = $this->run(['use', $notebookId]);
        if ($select === null || !$select->isSuccessful()) {
            $this->logger->warning('NotebookLM: failed to select notebook', [
                'notebookId' => $notebookId,
            ]);

            return false;
        }

        $add = $this->run(['source', 'add', $url]);
        if ($add === null || !$add->isSuccessful()) {
            $this->logger->warning('NotebookLM: failed to add URL source', [
                'notebookId' => $notebookId,
                'url' => $url,
                'error' => $add?->getErrorOutput(),
            ]);

            return false;
        }

        $this->logger->info('NotebookLM: source added', [
            'notebookId' => $notebookId,
            'url' => $url,
        ]);

        return true;
    }

    /**
     * Add text content as a source to a notebook.
     * Writes content to a temporary .md file and adds it.
     */
    public function addTextSource(string $notebookId, string $title, string $content): bool
    {
        if (!$this->enabled || $notebookId === '') {
            return false;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'notebooklm-src-') . '.md';
        $markdown = "# {$title}\n\n{$content}";
        file_put_contents($tmpFile, $markdown);

        try {
            $select = $this->run(['use', $notebookId]);
            if ($select === null || !$select->isSuccessful()) {
                return false;
            }

            $add = $this->run(['source', 'add', $tmpFile]);
            if ($add === null || !$add->isSuccessful()) {
                $this->logger->warning('NotebookLM: failed to add text source', [
                    'notebookId' => $notebookId,
                    'title' => $title,
                    'error' => $add?->getErrorOutput(),
                ]);

                return false;
            }

            $this->logger->info('NotebookLM: text source added', [
                'notebookId' => $notebookId,
                'title' => $title,
            ]);

            return true;
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * Ask a question within a notebook context.
     */
    public function ask(string $notebookId, string $question): ?string
    {
        if (!$this->enabled || $notebookId === '') {
            return null;
        }

        $select = $this->run(['use', $notebookId]);
        if ($select === null || !$select->isSuccessful()) {
            return null;
        }

        $ask = $this->run(['ask', $question]);
        if ($ask === null || !$ask->isSuccessful()) {
            $this->logger->warning('NotebookLM: ask failed', [
                'notebookId' => $notebookId,
                'question' => mb_substr($question, 0, 100),
                'error' => $ask?->getErrorOutput(),
            ]);

            return null;
        }

        return $this->cleanOutput($ask->getOutput());
    }

    /**
     * Generate Audio Overview for a notebook.
     *
     * @param string $format One of: deep-dive, brief, critique, debate
     */
    public function generateAudio(
        string $notebookId,
        string $instructions = '',
        string $format = 'brief',
        string $language = 'ro',
    ): ?string {
        if (!$this->enabled || $notebookId === '') {
            return null;
        }

        $select = $this->run(['use', $notebookId]);
        if ($select === null || !$select->isSuccessful()) {
            return null;
        }

        $args = ['generate', 'audio'];
        if ($instructions !== '') {
            $args[] = $instructions;
        }
        $args = [...$args, '--format', $format, '--language', $language, '--wait'];

        $gen = $this->run($args, self::AUDIO_TIMEOUT);
        if ($gen === null || !$gen->isSuccessful()) {
            $this->logger->warning('NotebookLM: audio generation failed', [
                'notebookId' => $notebookId,
                'format' => $format,
                'error' => $gen?->getErrorOutput(),
            ]);

            return null;
        }

        $this->logger->info('NotebookLM: audio generated', [
            'notebookId' => $notebookId,
            'format' => $format,
            'language' => $language,
        ]);

        return $this->cleanOutput($gen->getOutput());
    }

    /**
     * Download generated Audio Overview to a local path.
     */
    public function downloadAudio(string $notebookId, string $outputPath): bool
    {
        if (!$this->enabled || $notebookId === '') {
            return false;
        }

        $select = $this->run(['use', $notebookId]);
        if ($select === null || !$select->isSuccessful()) {
            return false;
        }

        $dl = $this->run(['download', 'audio', $outputPath], self::AUDIO_TIMEOUT);
        if ($dl === null || !$dl->isSuccessful()) {
            $this->logger->warning('NotebookLM: audio download failed', [
                'notebookId' => $notebookId,
                'outputPath' => $outputPath,
                'error' => $dl?->getErrorOutput(),
            ]);

            return false;
        }

        return true;
    }

    /**
     * Generate mind map JSON from notebook sources.
     *
     * @return array<string, mixed>|null
     */
    public function generateMindMap(string $notebookId): ?array
    {
        if (!$this->enabled || $notebookId === '') {
            return null;
        }

        $select = $this->run(['use', $notebookId]);
        if ($select === null || !$select->isSuccessful()) {
            return null;
        }

        $gen = $this->run(['generate', 'mind-map']);
        if ($gen === null || !$gen->isSuccessful()) {
            return null;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'notebooklm-mm-') . '.json';

        try {
            $dl = $this->run(['download', 'mind-map', $tmpFile]);
            if ($dl === null || !$dl->isSuccessful() || !file_exists($tmpFile)) {
                return null;
            }

            $data = json_decode(file_get_contents($tmpFile), true, 512, JSON_THROW_ON_ERROR);

            return is_array($data) ? $data : null;
        } catch (\JsonException $e) {
            $this->logger->warning('NotebookLM: mind map JSON parse failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * Generate a report/summary from notebook.
     */
    public function generateReport(string $notebookId, string $format = 'briefing', string $extra = ''): ?string
    {
        if (!$this->enabled || $notebookId === '') {
            return null;
        }

        $select = $this->run(['use', $notebookId]);
        if ($select === null || !$select->isSuccessful()) {
            return null;
        }

        $args = ['generate', 'report', '--format', $format];
        if ($extra !== '') {
            $args = [...$args, '--extra', $extra];
        }

        $gen = $this->run($args, self::AUDIO_TIMEOUT);
        if ($gen === null || !$gen->isSuccessful()) {
            $this->logger->warning('NotebookLM: report generation failed', [
                'notebookId' => $notebookId,
                'format' => $format,
                'error' => $gen?->getErrorOutput(),
            ]);

            return null;
        }

        return $this->cleanOutput($gen->getOutput());
    }

    /**
     * Get the notebook ID for a given topic.
     * Topic entity owns its notebookLmId directly (Sprint 51a — Topic-as-SSOT).
     */
    public function resolveNotebookId(Topic $topic): ?string
    {
        return $topic->getNotebookLmId();
    }

    /**
     * Ensure a NotebookLM notebook exists for a topic, creating one if needed.
     * Caller is responsible for flushing the EntityManager.
     */
    public function ensureNotebookForTopic(Topic $topic): ?string
    {
        $existing = $topic->getNotebookLmId();
        if ($existing !== null) {
            return $existing;
        }

        if (!$this->isAvailable()) {
            return null;
        }

        $title = sprintf('Deschide — %s', $topic->getTitle());
        $process = $this->run(['notebook', 'create', '--title', $title]);

        if ($process === null || !$process->isSuccessful()) {
            $this->logger->warning('NotebookLM: failed to create notebook for topic', [
                'topicId' => $topic->getId(),
                'topicTitle' => $topic->getTitle(),
                'error' => $process?->getErrorOutput(),
            ]);

            return null;
        }

        $notebookId = $this->cleanOutput($process->getOutput());
        if ($notebookId === '') {
            return null;
        }

        $topic->setNotebookLmId($notebookId);

        $this->logger->info('NotebookLM: notebook created for topic', [
            'topicId' => $topic->getId(),
            'notebookId' => $notebookId,
        ]);

        return $notebookId;
    }

    /**
     * Execute a notebooklm CLI command.
     *
     * @param list<string> $args
     */
    private function run(array $args, int $timeout = self::DEFAULT_TIMEOUT): ?Process
    {
        $command = [$this->cliPath, ...$args];

        try {
            $process = new Process($command);
            $process->setTimeout($timeout);
            $process->run();

            return $process;
        } catch (\Throwable $e) {
            $this->logger->error('NotebookLM: process execution failed', [
                'command' => implode(' ', $args),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Strip ANSI escape codes and trim whitespace from CLI output.
     */
    private function cleanOutput(string $output): string
    {
        // Strip ANSI escape sequences
        $clean = (string) preg_replace('/\x1B\[[0-9;]*[a-zA-Z]/', '', $output);

        return trim($clean);
    }
}
