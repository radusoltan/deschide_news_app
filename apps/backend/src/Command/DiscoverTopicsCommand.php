<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Topic\TopicDiscoveryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:topics:discover',
    description: 'Discover new topics from untagged PressRelease articles using Gemini CLI',
)]
final class DiscoverTopicsCommand extends Command
{
    public function __construct(
        private readonly TopicDiscoveryService $discoveryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('hours', null, InputOption::VALUE_REQUIRED, 'Hours to look back', '48')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show proposals without creating topics');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = (int) $input->getOption('hours');
        $dryRun = $input->getOption('dry-run');

        $io->title('Topic Discovery');
        $io->info(sprintf('Analyzing PressReleases from the last %d hours...', $hours));

        try {
        $proposals = $this->discoveryService->discoverNewTopics($hours);

        if (empty($proposals)) {
            $io->warning('No new topic proposals found.');

            return Command::SUCCESS;
        }

        $io->section(sprintf('Found %d Topic Proposals', \count($proposals)));
        $io->table(
            ['Name (RO)', 'Name (EN)', 'Name (RU)', 'Confidence', 'Articles', 'Parent'],
            array_map(fn ($p) => [
                $p->proposedNameRo,
                $p->proposedNameEn,
                $p->proposedNameRu,
                number_format($p->confidence * 100, 0) . '%',
                \count($p->relatedArticleIds),
                $p->suggestedParentTopic ?? '-',
            ], $proposals),
        );

        if ($dryRun) {
            $io->note('Dry run — no topics created.');
        } else {
            $io->success(sprintf('Created %d pending topics for review.', \count($proposals)));
        }

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Topic discovery failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
