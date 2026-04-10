<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UrlRedirectRepository;
use App\Service\SlugLookupService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:redirects:consolidate',
    description: 'Consolidate redirect chains to improve performance',
)]
class RedirectConsolidateCommand extends Command
{
    public function __construct(
        private UrlRedirectRepository $redirectRepository,
        private SlugLookupService $slugLookupService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate consolidation without making changes')
            ->addOption('min-chain-length', null, InputOption::VALUE_REQUIRED, 'Minimum chain length to consolidate (default: 3)', '3')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of chains to process (default: 100)', '100')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force consolidation without confirmation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $minChainLength = (int) $input->getOption('min-chain-length');
        $limit = (int) $input->getOption('limit');
        $force = $input->getOption('force');

        $io->title('Redirect Chain Consolidation');

        // Display configuration
        $io->section('Configuration');
        $io->table(
            ['Option', 'Value'],
            [
                ['Dry Run', $dryRun ? 'Yes' : 'No'],
                ['Min Chain Length', $minChainLength],
                ['Limit', $limit],
                ['Force', $force ? 'Yes' : 'No'],
            ]
        );

        try {
        // Find redirect chains
        $io->section('Finding Redirect Chains');
        $io->text('Analyzing redirects...');

        $allRedirects = $this->redirectRepository->findAll();
        $problematicChains = [];
        $checkedUrls = [];
        $totalChecked = 0;

        $progressBar = $io->createProgressBar(min(\count($allRedirects), $limit));

        foreach ($allRedirects as $redirect) {
            if ($totalChecked >= $limit) {
                break;
            }

            $oldUrl = $redirect->getOldUrl();
            if (isset($checkedUrls[$oldUrl])) {
                continue;
            }

            ++$totalChecked;
            $chainResult = $this->slugLookupService->getRedirectChain($oldUrl);

            if ($chainResult['chain_length'] >= $minChainLength) {
                $totalHits = array_sum(array_map(fn ($r) => $r->getHitCount(), $chainResult['redirects']));

                $problematicChains[] = [
                    'start_url' => $oldUrl,
                    'final_url' => $chainResult['final_url'],
                    'chain_length' => $chainResult['chain_length'],
                    'total_hits' => $totalHits,
                    'redirects' => $chainResult['redirects'],
                ];
            }

            $checkedUrls[$oldUrl] = true;
            $progressBar->advance();
        }

        $progressBar->finish();
        $io->newLine(2);

        if (empty($problematicChains)) {
            $io->success('No redirect chains found matching the criteria.');

            return Command::SUCCESS;
        }

        $io->text(\sprintf('Found %d redirect chains to consolidate', \count($problematicChains)));

        // Display sample chains
        $io->section('Sample Chains (first 5)');
        $sampleChains = \array_slice($problematicChains, 0, 5);
        foreach ($sampleChains as $i => $chain) {
            $io->text(\sprintf(
                '%d. %s → %s (%d hops, %d total hits)',
                $i + 1,
                substr($chain['start_url'], 0, 50),
                substr($chain['final_url'], 0, 50),
                $chain['chain_length'],
                $chain['total_hits']
            ));
        }

        if (\count($problematicChains) > 5) {
            $io->text(\sprintf('... and %d more chains', \count($problematicChains) - 5));
        }

        // Statistics
        $io->section('Chain Statistics');
        $totalChainLength = array_sum(array_column($problematicChains, 'chain_length'));
        $avgChainLength = $totalChainLength / \count($problematicChains);
        $maxChainLength = max(array_column($problematicChains, 'chain_length'));
        $totalHits = array_sum(array_column($problematicChains, 'total_hits'));

        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Chains', \count($problematicChains)],
                ['Avg Chain Length', round($avgChainLength, 2)],
                ['Max Chain Length', $maxChainLength],
                ['Total Hits (all chains)', $totalHits],
                ['Redirects to Remove', $totalChainLength - \count($problematicChains)],
            ]
        );

        // Dry run mode
        if ($dryRun) {
            $io->warning('DRY RUN MODE: No changes will be made');
            $io->note(\sprintf(
                'Would consolidate %d chains, removing %d intermediate redirects',
                \count($problematicChains),
                $totalChainLength - \count($problematicChains)
            ));

            return Command::SUCCESS;
        }

        // Confirmation
        if (!$force) {
            $confirm = $io->confirm(
                \sprintf('Consolidate %d redirect chains?', \count($problematicChains)),
                false
            );

            if (!$confirm) {
                $io->warning('Consolidation cancelled by user');

                return Command::SUCCESS;
            }
        }

        // Consolidate chains
        $io->section('Consolidating Chains');
        $io->progressStart(\count($problematicChains));

        $consolidatedCount = 0;
        $removedCount = 0;

        foreach ($problematicChains as $chain) {
            $startUrl = $chain['start_url'];
            $finalUrl = $chain['final_url'];
            $redirects = $chain['redirects'];

            // Find the first redirect in the chain
            $firstRedirect = $this->redirectRepository->findOneBy(['oldUrl' => $startUrl]);

            if (!$firstRedirect) {
                $io->progressAdvance();
                continue;
            }

            // Update first redirect to point directly to final URL
            $firstRedirect->setNewUrl($finalUrl);

            // Calculate combined hit count
            $combinedHits = array_sum(array_map(fn ($r) => $r->getHitCount(), $redirects));
            $firstRedirect->setHitCount($combinedHits);

            // Remove intermediate redirects
            for ($i = 1; $i < \count($redirects); ++$i) {
                $this->entityManager->remove($redirects[$i]);
                ++$removedCount;
            }

            ++$consolidatedCount;
            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        // Summary
        $io->newLine(2);
        $io->success(\sprintf('Successfully consolidated %d redirect chains', $consolidatedCount));

        $io->table(
            ['Metric', 'Value'],
            [
                ['Chains Consolidated', $consolidatedCount],
                ['Intermediate Redirects Removed', $removedCount],
                ['Performance Improvement', \sprintf('%d fewer hops', $removedCount)],
            ]
        );

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Redirect consolidation failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
