<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Logging\DebugStack;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:db:audit',
    description: 'Audit database queries for N+1 problems'
)]
class DatabaseQueryAuditCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('endpoint', 'e', InputOption::VALUE_REQUIRED, 'API endpoint to test', '/api/articles')
            ->addOption('limit', 'l', InputOption::VALUE_OPTIONAL, 'Limit items', '30')
            ->addOption('locale', null, InputOption::VALUE_OPTIONAL, 'Locale to test', 'ro');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $endpoint = $input->getOption('endpoint');
        $limit = $input->getOption('limit');
        $locale = $input->getOption('locale');

        $io->title('Database Query Audit');
        $io->info("Testing endpoint: {$endpoint}");
        $io->info("Locale: {$locale}, Limit: {$limit}");

        // Enable SQL logging
        $sqlLogger = new DebugStack();
        $this->em->getConnection()->getConfiguration()->setMiddlewares([
            new LoggingMiddleware($sqlLogger),
        ]);

        $io->section('Executing request...');

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Make HTTP request to test endpoint
        try {
            $url = "http://127.0.0.1:8081{$endpoint}?itemsPerPage={$limit}";
            $response = $this->httpClient->request('GET', $url, [
                'headers' => [
                    'Accept-Language' => $locale,
                    'Accept' => 'application/ld+json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();

            $endTime = microtime(true);
            $endMemory = memory_get_usage(true);

            $io->success("Request completed: HTTP {$statusCode}");

        } catch (Exception $e) {
            $io->error("Request failed: {$e->getMessage()}");

            return Command::FAILURE;
        }

        // Analyze queries
        $queries = $sqlLogger->queries ?? [];
        $queryCount = \count($queries);

        $io->section('Performance Metrics');
        $io->horizontalTable(
            ['Metric', 'Value'],
            [
                ['Total Queries', $queryCount],
                ['Execution Time', \sprintf('%.3f seconds', $endTime - $startTime)],
                ['Memory Used', $this->formatBytes($endMemory - $startMemory)],
                ['Avg Query Time', \sprintf('%.4f ms', ($endTime - $startTime) * 1000 / max($queryCount, 1))],
            ]
        );

        // Detect N+1 patterns
        $io->section('Query Analysis');

        $groupedQueries = [];
        $potentialN1 = [];

        foreach ($queries as $query) {
            $sql = $query['sql'] ?? '';
            $params = $query['params'] ?? [];

            // Normalize query (remove specific IDs)
            $normalized = preg_replace('/\b\d+\b/', '?', $sql);

            if (!isset($groupedQueries[$normalized])) {
                $groupedQueries[$normalized] = [
                    'count' => 0,
                    'example' => $sql,
                    'params' => $params,
                ];
            }

            ++$groupedQueries[$normalized]['count'];

            // Detect potential N+1
            if ($groupedQueries[$normalized]['count'] > 3) {
                $potentialN1[$normalized] = $groupedQueries[$normalized];
            }
        }

        if (!empty($potentialN1)) {
            $io->warning(\sprintf('Found %d potential N+1 query patterns!', \count($potentialN1)));

            $rows = [];
            foreach ($potentialN1 as $pattern => $data) {
                $rows[] = [
                    $data['count'],
                    $this->truncate($data['example'], 80),
                ];
            }

            $io->table(['Count', 'Query Pattern'], $rows);
        } else {
            $io->success('No N+1 query patterns detected!');
        }

        // Query distribution
        $io->section('Query Distribution');

        $selectQueries = array_filter($queries, fn ($q) => str_starts_with(strtoupper($q['sql'] ?? ''), 'SELECT'));
        $updateQueries = array_filter($queries, fn ($q) => str_starts_with(strtoupper($q['sql'] ?? ''), 'UPDATE'));
        $insertQueries = array_filter($queries, fn ($q) => str_starts_with(strtoupper($q['sql'] ?? ''), 'INSERT'));
        $deleteQueries = array_filter($queries, fn ($q) => str_starts_with(strtoupper($q['sql'] ?? ''), 'DELETE'));

        $io->horizontalTable(
            ['Type', 'Count'],
            [
                ['SELECT', \count($selectQueries)],
                ['INSERT', \count($insertQueries)],
                ['UPDATE', \count($updateQueries)],
                ['DELETE', \count($deleteQueries)],
            ]
        );

        // Recommendations
        $io->section('Recommendations');

        if ($queryCount > 50) {
            $io->warning("High query count ({$queryCount} queries). Consider:");
            $io->listing([
                'Add eager loading with leftJoin() + addSelect()',
                'Enable Doctrine result cache',
                'Use fewer nested relationships',
                'Consider denormalization for read-heavy endpoints',
            ]);
        }

        if (!empty($potentialN1)) {
            $io->warning('N+1 queries detected. Fix by:');
            $io->listing([
                'Using QueryBuilder with leftJoin() and addSelect()',
                'Adding @ORM\Fetch("EAGER") on associations',
                'Using Doctrine EXTRA_LAZY fetch mode',
                'Implementing custom State Providers with proper eager loading',
            ]);
        }

        if ($queryCount <= 10 && empty($potentialN1)) {
            $io->success('✅ Query optimization looks good!');
        }

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return \sprintf('%.2f MB', $bytes / 1048576);
        }
        if ($bytes >= 1024) {
            return \sprintf('%.2f KB', $bytes / 1024);
        }

        return $bytes . ' B';
    }

    private function truncate(string $str, int $length): string
    {
        if (\strlen($str) <= $length) {
            return $str;
        }

        return substr($str, 0, $length) . '...';
    }
}
