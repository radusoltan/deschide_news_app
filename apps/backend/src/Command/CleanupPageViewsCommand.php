<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * GDPR-compliant cleanup command for page_views table.
 *
 * This command:
 * - Anonymizes IP addresses older than 7 days (keeps statistics, removes PII)
 * - Deletes records older than 90 days (data minimization)
 *
 * Recommended: Run daily via cron at 3 AM
 * Example crontab entry:
 * 0 3 * * * cd /var/www/deschide_news_app/apps/backend && php bin/console app:cleanup-page-views
 */
#[AsCommand(
    name: 'app:cleanup-page-views',
    description: 'GDPR-compliant cleanup of page_views table (anonymize IPs, delete old records)'
)]
class CleanupPageViewsCommand extends Command
{
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be done without making changes'
            )
            ->addOption(
                'anonymize-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Anonymize IPs older than X days',
                '7'
            )
            ->addOption(
                'delete-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Delete records older than X days',
                '90'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $anonymizeDays = (int) $input->getOption('anonymize-days');
        $deleteDays = (int) $input->getOption('delete-days');

        $io->title('GDPR Page Views Cleanup');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No changes will be made');
        }

        $io->section('Configuration');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Anonymize IPs after', "{$anonymizeDays} days"],
                ['Delete records after', "{$deleteDays} days"],
            ]
        );

        try {
            // Get current stats
            $stats = $this->getCurrentStats();
            $io->section('Current Statistics');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total records', number_format($stats['total'])],
                    ['Records > 7 days', number_format($stats['older_than_7_days'])],
                    ['Records > 90 days', number_format($stats['older_than_90_days'])],
                    ['Non-anonymized IPs (> 7 days)', number_format($stats['to_anonymize'])],
                ]
            );

            if ($dryRun) {
                $io->success('Dry run complete. No changes made.');

                return Command::SUCCESS;
            }

            // Step 1: Anonymize IPs older than X days
            $io->section('Step 1: Anonymizing IP Addresses');
            $anonymizedCount = $this->anonymizeOldIps($anonymizeDays);
            $io->writeln("Anonymized: {$anonymizedCount} IP addresses");

            // Step 2: Delete records older than X days
            $io->section('Step 2: Deleting Old Records');
            $deletedCount = $this->deleteOldRecords($deleteDays);
            $io->writeln("Deleted: {$deletedCount} records");

            $io->success(\sprintf(
                'Cleanup complete! Anonymized %d IPs, deleted %d records.',
                $anonymizedCount,
                $deletedCount
            ));

            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Cleanup failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function getCurrentStats(): array
    {
        $result = $this->connection->fetchAssociative("
            SELECT
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE viewed_at < NOW() - INTERVAL '7 days') as older_than_7_days,
                COUNT(*) FILTER (WHERE viewed_at < NOW() - INTERVAL '90 days') as older_than_90_days,
                COUNT(*) FILTER (
                    WHERE viewed_at < NOW() - INTERVAL '7 days'
                    AND ip_address IS NOT NULL
                    AND ip_address NOT LIKE '%.0'
                    AND ip_address NOT LIKE '%::'
                ) as to_anonymize
            FROM page_views
        ");

        return [
            'total' => (int) ($result['total'] ?? 0),
            'older_than_7_days' => (int) ($result['older_than_7_days'] ?? 0),
            'older_than_90_days' => (int) ($result['older_than_90_days'] ?? 0),
            'to_anonymize' => (int) ($result['to_anonymize'] ?? 0),
        ];
    }

    private function anonymizeOldIps(int $days): int
    {
        // Use the PostgreSQL function we created
        $result = $this->connection->executeStatement("
            UPDATE page_views
            SET ip_address = anonymize_ip(ip_address)
            WHERE viewed_at < NOW() - INTERVAL '{$days} days'
            AND ip_address IS NOT NULL
            AND ip_address NOT LIKE '%.0'
            AND ip_address NOT LIKE '%::'
        ");

        return $result;
    }

    private function deleteOldRecords(int $days): int
    {
        $result = $this->connection->executeStatement("
            DELETE FROM page_views
            WHERE viewed_at < NOW() - INTERVAL '{$days} days'
        ");

        return $result;
    }
}
