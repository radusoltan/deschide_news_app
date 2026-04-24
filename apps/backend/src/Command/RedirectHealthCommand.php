<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UrlRedirectRepository;
use App\Service\SlugLookupService;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:redirects:health',
    description: 'Run health checks on redirect system',
)]
class RedirectHealthCommand extends Command
{
    public function __construct(
        private UrlRedirectRepository $redirectRepository,
        private SlugLookupService $slugLookupService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('check-limit', null, InputOption::VALUE_REQUIRED, 'Maximum redirects to check for chains (default: 100)', '100')
            ->addOption('fail-on-warning', null, InputOption::VALUE_NONE, 'Exit with failure code if warnings found')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output results as JSON')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $checkLimit = (int) $input->getOption('check-limit');
        $failOnWarning = $input->getOption('fail-on-warning');
        $jsonOutput = $input->getOption('json');

        if (!$jsonOutput) {
            $io->title('Redirect System Health Check');
        }

        try {
        // Initialize health report
        $healthReport = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'healthy',
            'checks' => [],
            'issues' => [],
            'recommendations' => [],
        ];

        // Check 1: Overall statistics
        $statistics = $this->redirectRepository->getStatistics();
        $total = $statistics['total'];
        $unused = $statistics['unused'];

        $healthReport['checks']['total_redirects'] = [
            'status' => 'pass',
            'value' => $total,
        ];

        // Check 2: Unused redirects
        $unusedPercentage = $total > 0 ? ($unused / $total) * 100 : 0;
        if ($unusedPercentage > 20) {
            $healthReport['status'] = 'warning';
            $healthReport['checks']['unused_redirects'] = [
                'status' => 'warning',
                'value' => $unused,
                'percentage' => round($unusedPercentage, 2),
            ];
            $healthReport['issues'][] = [
                'type' => 'unused_redirects',
                'severity' => 'warning',
                'description' => \sprintf('High percentage of unused redirects: %d (%.2f%%)', $unused, $unusedPercentage),
            ];
            $healthReport['recommendations'][] = 'Run: php bin/console app:redirects:cleanup --dry-run to review unused redirects';
        } elseif ($unusedPercentage > 10) {
            $healthReport['status'] = $healthReport['status'] === 'healthy' ? 'warning' : $healthReport['status'];
            $healthReport['checks']['unused_redirects'] = [
                'status' => 'warning',
                'value' => $unused,
                'percentage' => round($unusedPercentage, 2),
            ];
            $healthReport['issues'][] = [
                'type' => 'unused_redirects',
                'severity' => 'info',
                'description' => \sprintf('Moderate percentage of unused redirects: %d (%.2f%%)', $unused, $unusedPercentage),
            ];
        } else {
            $healthReport['checks']['unused_redirects'] = [
                'status' => 'pass',
                'value' => $unused,
                'percentage' => round($unusedPercentage, 2),
            ];
        }

        // Check 3: Old redirects (> 6 months with 0 hits)
        $sixMonthsAgo = new DateTimeImmutable('-6 months');
        $oldRedirects = $this->redirectRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.createdAt < :date')
            ->andWhere('r.hitCount = 0')
            ->setParameter('date', $sixMonthsAgo)
            ->getQuery()
            ->getSingleScalarResult();

        if ($oldRedirects > 0) {
            $healthReport['checks']['old_redirects'] = [
                'status' => 'info',
                'value' => (int) $oldRedirects,
            ];
            $healthReport['issues'][] = [
                'type' => 'old_redirects',
                'severity' => 'info',
                'description' => \sprintf('Found %d redirects older than 6 months with no hits', $oldRedirects),
            ];
            $healthReport['recommendations'][] = 'Consider cleaning up old unused redirects: php bin/console app:redirects:cleanup --older-than=180';
        } else {
            $healthReport['checks']['old_redirects'] = [
                'status' => 'pass',
                'value' => 0,
            ];
        }

        // Check 4: Redirect chains
        if (!$jsonOutput) {
            $io->text('Checking for redirect chains...');
        }

        $allRedirects = $this->redirectRepository->findAll();
        $longChains = 0;
        $circularChains = 0;
        $checkedUrls = [];
        $problematicChains = [];

        $limit = min(\count($allRedirects), $checkLimit);
        foreach (\array_slice($allRedirects, 0, $limit) as $redirect) {
            $oldUrl = $redirect->getOldUrl();
            if (isset($checkedUrls[$oldUrl])) {
                continue;
            }

            $chain = $this->slugLookupService->getRedirectChain($oldUrl);

            if ($chain['chain_length'] > 3) {
                ++$longChains;
                if (\count($problematicChains) < 5) {
                    $problematicChains[] = [
                        'start_url' => $oldUrl,
                        'final_url' => $chain['final_url'],
                        'length' => $chain['chain_length'],
                    ];
                }
            }

            if ($chain['circular']) {
                ++$circularChains;
            }

            $checkedUrls[$oldUrl] = true;

            if (\count($checkedUrls) >= $checkLimit) {
                break;
            }
        }

        if ($longChains > 0) {
            $healthReport['status'] = 'warning';
            $healthReport['checks']['long_chains'] = [
                'status' => 'warning',
                'value' => $longChains,
                'sample' => $problematicChains,
            ];
            $healthReport['issues'][] = [
                'type' => 'long_chains',
                'severity' => 'warning',
                'description' => \sprintf('Found %d redirect chains with more than 3 hops', $longChains),
            ];
            $healthReport['recommendations'][] = 'Run: php bin/console app:redirects:consolidate --dry-run to review chains';
        } else {
            $healthReport['checks']['long_chains'] = [
                'status' => 'pass',
                'value' => 0,
            ];
        }

        if ($circularChains > 0) {
            $healthReport['status'] = 'critical';
            $healthReport['checks']['circular_chains'] = [
                'status' => 'critical',
                'value' => $circularChains,
            ];
            $healthReport['issues'][] = [
                'type' => 'circular_chains',
                'severity' => 'critical',
                'description' => \sprintf('Found %d circular redirect chains (CRITICAL)', $circularChains),
            ];
            $healthReport['recommendations'][] = 'URGENT: Manually review and fix circular redirects';
        } else {
            $healthReport['checks']['circular_chains'] = [
                'status' => 'pass',
                'value' => 0,
            ];
        }

        // Check 5: Invalid redirects (redirecting to themselves)
        $selfRedirects = $this->redirectRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.oldUrl = r.newUrl')
            ->getQuery()
            ->getSingleScalarResult();

        if ($selfRedirects > 0) {
            $healthReport['status'] = 'critical';
            $healthReport['checks']['self_redirects'] = [
                'status' => 'critical',
                'value' => (int) $selfRedirects,
            ];
            $healthReport['issues'][] = [
                'type' => 'self_redirects',
                'severity' => 'critical',
                'description' => \sprintf('Found %d redirects pointing to themselves (CRITICAL)', $selfRedirects),
            ];
            $healthReport['recommendations'][] = 'URGENT: Delete self-referencing redirects immediately';
        } else {
            $healthReport['checks']['self_redirects'] = [
                'status' => 'pass',
                'value' => 0,
            ];
        }

        // Output results
        if ($jsonOutput) {
            $output->writeln(json_encode($healthReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->displayHealthReport($io, $healthReport);
        }

        // Determine exit code
        if ($healthReport['status'] === 'critical') {
            return Command::FAILURE;
        }

        if ($failOnWarning && $healthReport['status'] === 'warning') {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Health check failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function displayHealthReport(SymfonyStyle $io, array $report): void
    {
        // Overall status
        $io->newLine();
        $statusMessage = match ($report['status']) {
            'healthy' => '✅ Redirect system is healthy',
            'warning' => '⚠️  Redirect system has warnings',
            'critical' => '❌ Redirect system has critical issues',
            default => 'Unknown status',
        };

        if ($report['status'] === 'healthy') {
            $io->success($statusMessage);
        } elseif ($report['status'] === 'warning') {
            $io->warning($statusMessage);
        } else {
            $io->error($statusMessage);
        }

        // Health checks summary
        $io->section('Health Checks');
        $checksTable = [];
        foreach ($report['checks'] as $checkName => $checkData) {
            $statusIcon = match ($checkData['status']) {
                'pass' => '✅',
                'info' => 'ℹ️',
                'warning' => '⚠️',
                'critical' => '❌',
                default => '?',
            };

            $value = $checkData['value'];
            if (isset($checkData['percentage'])) {
                $value .= \sprintf(' (%.2f%%)', $checkData['percentage']);
            }

            $checksTable[] = [
                $statusIcon,
                ucwords(str_replace('_', ' ', $checkName)),
                $value,
                ucfirst($checkData['status']),
            ];
        }
        $io->table(['', 'Check', 'Value', 'Status'], $checksTable);

        // Issues
        if (!empty($report['issues'])) {
            $io->section('Issues Found');
            foreach ($report['issues'] as $issue) {
                $severityIcon = match ($issue['severity']) {
                    'info' => 'ℹ️',
                    'warning' => '⚠️',
                    'critical' => '❌',
                    default => '•',
                };

                $io->text(\sprintf(
                    '%s [%s] %s',
                    $severityIcon,
                    strtoupper($issue['severity']),
                    $issue['description']
                ));
            }
        }

        // Sample problematic chains
        if (isset($report['checks']['long_chains']['sample']) && !empty($report['checks']['long_chains']['sample'])) {
            $io->section('Sample Long Chains');
            foreach ($report['checks']['long_chains']['sample'] as $i => $chain) {
                $io->text(\sprintf(
                    '%d. %s → %s (%d hops)',
                    $i + 1,
                    substr($chain['start_url'], 0, 50),
                    substr($chain['final_url'], 0, 50),
                    $chain['length']
                ));
            }
        }

        // Recommendations
        if (!empty($report['recommendations'])) {
            $io->section('Recommendations');
            $io->listing($report['recommendations']);
        }

        $io->newLine();
        $io->text(\sprintf('Health check completed at: %s', $report['timestamp']));
    }
}
