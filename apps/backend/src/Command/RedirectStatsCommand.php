<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UrlRedirectRepository;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:redirects:stats',
    description: 'Display comprehensive redirect statistics',
)]
class RedirectStatsCommand extends Command
{
    public function __construct(
        private UrlRedirectRepository $redirectRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format (table, json, csv)', 'table')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Show detailed statistics')
            ->addOption('export', null, InputOption::VALUE_REQUIRED, 'Export to file (requires json or csv format)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $format = $input->getOption('format');
        $detailed = $input->getOption('detailed');
        $exportFile = $input->getOption('export');

        if (!\in_array($format, ['table', 'json', 'csv'], true)) {
            $io->error('Invalid format. Must be: table, json, or csv');

            return Command::FAILURE;
        }

        if ($exportFile && $format === 'table') {
            $io->error('Export requires json or csv format');

            return Command::FAILURE;
        }

        // Only show title for table format
        if ($format === 'table') {
            $io->title('Redirect Statistics');
        }

        // Get statistics
        $statistics = $this->redirectRepository->getStatistics();

        // Get additional metrics
        $allRedirects = $this->redirectRepository->findAll();
        $totalHits = array_sum(array_map(fn ($r) => $r->getHitCount(), $allRedirects));

        // Calculate age statistics
        $now = new DateTimeImmutable();
        $ageStats = [
            'less_than_1_month' => 0,
            '1_to_3_months' => 0,
            '3_to_6_months' => 0,
            'more_than_6_months' => 0,
        ];

        foreach ($allRedirects as $redirect) {
            $age = $now->diff($redirect->getCreatedAt())->days;

            if ($age < 30) {
                ++$ageStats['less_than_1_month'];
            } elseif ($age < 90) {
                ++$ageStats['1_to_3_months'];
            } elseif ($age < 180) {
                ++$ageStats['3_to_6_months'];
            } else {
                ++$ageStats['more_than_6_months'];
            }
        }

        // Build comprehensive stats array
        $stats = [
            'overview' => [
                'total_redirects' => $statistics['total'],
                'total_hits' => $totalHits,
                'unused_redirects' => $statistics['unused'],
                'unused_percentage' => $statistics['total'] > 0 ? round(($statistics['unused'] / $statistics['total']) * 100, 2) : 0,
                'average_hits_per_redirect' => $statistics['total'] > 0 ? round($totalHits / $statistics['total'], 2) : 0,
            ],
            'by_type' => $statistics['by_type'],
            'by_age' => $ageStats,
            'most_used' => \array_slice($statistics['most_used'], 0, 10),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        // Add detailed statistics if requested
        if ($detailed) {
            // Status code distribution
            $statusCodeStats = [];
            foreach ($allRedirects as $redirect) {
                $statusCode = $redirect->getHttpStatusCode();
                if (!isset($statusCodeStats[$statusCode])) {
                    $statusCodeStats[$statusCode] = 0;
                }
                ++$statusCodeStats[$statusCode];
            }
            $stats['by_status_code'] = $statusCodeStats;

            // Locale distribution
            $localeStats = [];
            foreach ($allRedirects as $redirect) {
                $locale = $redirect->getLocale();
                if (!isset($localeStats[$locale])) {
                    $localeStats[$locale] = 0;
                }
                ++$localeStats[$locale];
            }
            $stats['by_locale'] = $localeStats;

            // Recent activity
            $recentRedirects = $this->redirectRepository->createQueryBuilder('r')
                ->where('r.lastAccessedAt IS NOT NULL')
                ->orderBy('r.lastAccessedAt', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();

            $stats['recent_activity'] = array_map(fn ($r) => [
                'old_url' => $r->getOldUrl(),
                'new_url' => $r->getNewUrl(),
                'hit_count' => $r->getHitCount(),
                'last_accessed' => $r->getLastAccessedAt()?->format('Y-m-d H:i:s'),
            ], $recentRedirects);
        }

        // Output based on format
        switch ($format) {
            case 'json':
                $jsonOutput = json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                if ($exportFile) {
                    file_put_contents($exportFile, $jsonOutput);
                    $io->success(\sprintf('Statistics exported to %s', $exportFile));
                } else {
                    $output->writeln($jsonOutput);
                }
                break;

            case 'csv':
                $csvData = $this->convertToCSV($stats);
                if ($exportFile) {
                    file_put_contents($exportFile, $csvData);
                    $io->success(\sprintf('Statistics exported to %s', $exportFile));
                } else {
                    $output->writeln($csvData);
                }
                break;

            case 'table':
            default:
                $this->displayTable($io, $stats, $detailed);
                break;
        }

        return Command::SUCCESS;
    }

    private function displayTable(SymfonyStyle $io, array $stats, bool $detailed): void
    {
        // Overview
        $io->section('Overview');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Redirects', $stats['overview']['total_redirects']],
                ['Total Hits', number_format($stats['overview']['total_hits'])],
                ['Unused Redirects', \sprintf('%d (%.2f%%)', $stats['overview']['unused_redirects'], $stats['overview']['unused_percentage'])],
                ['Avg Hits/Redirect', $stats['overview']['average_hits_per_redirect']],
            ]
        );

        // By Type
        $io->section('Redirects by Type');
        $typeTable = [];
        foreach ($stats['by_type'] as $type => $count) {
            $typeTable[] = [$type, $count];
        }
        $io->table(['Type', 'Count'], $typeTable);

        // By Age
        $io->section('Redirects by Age');
        $io->table(
            ['Age Range', 'Count'],
            [
                ['< 1 month', $stats['by_age']['less_than_1_month']],
                ['1-3 months', $stats['by_age']['1_to_3_months']],
                ['3-6 months', $stats['by_age']['3_to_6_months']],
                ['> 6 months', $stats['by_age']['more_than_6_months']],
            ]
        );

        // Most Used
        if (!empty($stats['most_used'])) {
            $io->section('Most Used Redirects (Top 10)');
            $mostUsedTable = [];
            foreach ($stats['most_used'] as $redirect) {
                $mostUsedTable[] = [
                    substr($redirect['oldUrl'], 0, 40),
                    substr($redirect['newUrl'], 0, 40),
                    $redirect['hitCount'],
                ];
            }
            $io->table(['Old URL', 'New URL', 'Hits'], $mostUsedTable);
        }

        // Detailed statistics
        if ($detailed) {
            // Status Codes
            if (isset($stats['by_status_code'])) {
                $io->section('By Status Code');
                $statusTable = [];
                foreach ($stats['by_status_code'] as $code => $count) {
                    $statusTable[] = [$code, $count];
                }
                $io->table(['Status Code', 'Count'], $statusTable);
            }

            // Locales
            if (isset($stats['by_locale'])) {
                $io->section('By Locale');
                $localeTable = [];
                foreach ($stats['by_locale'] as $locale => $count) {
                    $localeTable[] = [$locale, $count];
                }
                $io->table(['Locale', 'Count'], $localeTable);
            }

            // Recent Activity
            if (isset($stats['recent_activity']) && !empty($stats['recent_activity'])) {
                $io->section('Recent Activity (Last 10 Accesses)');
                $activityTable = [];
                foreach ($stats['recent_activity'] as $activity) {
                    $activityTable[] = [
                        substr($activity['old_url'], 0, 30),
                        substr($activity['new_url'], 0, 30),
                        $activity['hit_count'],
                        $activity['last_accessed'],
                    ];
                }
                $io->table(['Old URL', 'New URL', 'Hits', 'Last Access'], $activityTable);
            }
        }

        $io->newLine();
        $io->text(\sprintf('Generated at: %s', $stats['timestamp']));
    }

    private function convertToCSV(array $stats): string
    {
        $csv = [];

        // Header
        $csv[] = 'Redirect Statistics Report';
        $csv[] = 'Generated: ' . $stats['timestamp'];
        $csv[] = '';

        // Overview
        $csv[] = 'OVERVIEW';
        $csv[] = 'Metric,Value';
        $csv[] = \sprintf('Total Redirects,%d', $stats['overview']['total_redirects']);
        $csv[] = \sprintf('Total Hits,%d', $stats['overview']['total_hits']);
        $csv[] = \sprintf('Unused Redirects,%d', $stats['overview']['unused_redirects']);
        $csv[] = \sprintf('Unused Percentage,%.2f%%', $stats['overview']['unused_percentage']);
        $csv[] = \sprintf('Average Hits per Redirect,%.2f', $stats['overview']['average_hits_per_redirect']);
        $csv[] = '';

        // By Type
        $csv[] = 'BY TYPE';
        $csv[] = 'Type,Count';
        foreach ($stats['by_type'] as $type => $count) {
            $csv[] = \sprintf('%s,%d', $type, $count);
        }
        $csv[] = '';

        // By Age
        $csv[] = 'BY AGE';
        $csv[] = 'Age Range,Count';
        $csv[] = \sprintf('< 1 month,%d', $stats['by_age']['less_than_1_month']);
        $csv[] = \sprintf('1-3 months,%d', $stats['by_age']['1_to_3_months']);
        $csv[] = \sprintf('3-6 months,%d', $stats['by_age']['3_to_6_months']);
        $csv[] = \sprintf('> 6 months,%d', $stats['by_age']['more_than_6_months']);
        $csv[] = '';

        // Most Used
        if (!empty($stats['most_used'])) {
            $csv[] = 'MOST USED REDIRECTS';
            $csv[] = 'Old URL,New URL,Hits';
            foreach ($stats['most_used'] as $redirect) {
                $csv[] = \sprintf(
                    '"%s","%s",%d',
                    str_replace('"', '""', $redirect['oldUrl']),
                    str_replace('"', '""', $redirect['newUrl']),
                    $redirect['hitCount']
                );
            }
        }

        return implode("\n", $csv);
    }
}
