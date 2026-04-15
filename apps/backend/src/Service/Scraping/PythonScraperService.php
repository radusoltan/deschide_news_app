<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Invokes the Python deschide-scraper CLI via Symfony Process.
 *
 * Same pattern as GeminiCliService: subprocess → JSON on stdout → parse.
 * Logs and errors go to stderr (Python side) and are captured for debugging.
 */
class PythonScraperService
{
    private const VALID_TYPES = ['gov-rss', 'dom-scraper', 'pdf-extractor'];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $pythonBin = '/usr/bin/python3',
        private readonly string $scraperPath = '/var/www/deschide_news_app/tools/python-scraper',
    ) {}

    /**
     * Fetch content from a URL using the Python scraper.
     *
     * @param string $url    Source URL to scrape
     * @param string $type   Extractor type: gov-rss, dom-scraper, pdf-extractor
     * @param int    $limit  Max items to return
     * @param int    $timeout Process timeout in seconds
     *
     * @return array{source_type: string, items: list<array{title: string, content: string, source_url: string, source_name: string, published_at: ?string, language: string, attachments: list<string>, excerpt: ?string}>, errors: list<string>, scraped_at: string}
     *
     * @throws PythonScraperException on process failure, timeout, or parse error
     */
    public function fetch(string $url, string $type, int $limit = 20, int $timeout = 60): array
    {
        if (!\in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid scraper type "%s". Valid: %s', $type, implode(', ', self::VALID_TYPES))
            );
        }

        $process = new Process(
            command: [
                $this->pythonBin,
                '-m',
                'deschide_scraper',
                'fetch',
                '--url=' . $url,
                '--type=' . $type,
                '--limit=' . $limit,
            ],
            cwd: $this->scraperPath,
            env: [
                'HOME' => getenv('HOME') ?: '/root',
                'PATH' => getenv('PATH') ?: '/usr/local/bin:/usr/bin:/bin',
                'PYTHONDONTWRITEBYTECODE' => '1',
            ],
            timeout: $timeout,
        );

        $this->logger->debug('PythonScraper: executing', [
            'url' => $url,
            'type' => $type,
            'limit' => $limit,
        ]);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            $this->logger->error('PythonScraper: process timed out', [
                'url' => $url,
                'type' => $type,
                'timeout' => $timeout,
            ]);

            throw new PythonScraperException(
                'Python scraper timed out after ' . $timeout . 's',
                0,
                $e,
                isTimeout: true,
            );
        }

        // Log stderr regardless of success (Python logs go there)
        $stderr = $process->getErrorOutput();
        if ($stderr !== '') {
            $this->logger->debug('PythonScraper: stderr', [
                'output' => mb_substr($stderr, 0, 500),
            ]);
        }

        if (!$process->isSuccessful()) {
            $this->logger->error('PythonScraper: process failed', [
                'exitCode' => $process->getExitCode(),
                'stderr' => mb_substr($stderr, 0, 500),
            ]);

            throw new PythonScraperException(
                'Python scraper failed (exit ' . ($process->getExitCode() ?? '?') . '): '
                . mb_substr($stderr, 0, 200),
                $process->getExitCode() ?? 1,
            );
        }

        $output = $process->getOutput();
        $decoded = json_decode($output, true);

        if (\json_last_error() !== \JSON_ERROR_NONE) {
            $this->logger->error('PythonScraper: JSON parse failed', [
                'error' => json_last_error_msg(),
                'outputPreview' => mb_substr($output, 0, 300),
            ]);

            throw new PythonScraperException(
                'Failed to parse Python scraper output: ' . json_last_error_msg()
            );
        }

        $itemCount = \count($decoded['items'] ?? []);

        $this->logger->info('PythonScraper: success', [
            'url' => $url,
            'type' => $type,
            'itemCount' => $itemCount,
            'errors' => $decoded['errors'] ?? [],
        ]);

        return $decoded;
    }

    /**
     * @return string[]
     */
    public static function getValidTypes(): array
    {
        return self::VALID_TYPES;
    }
}
