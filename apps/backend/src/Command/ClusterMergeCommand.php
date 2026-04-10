<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\StoryCluster;
use App\Service\Clustering\ImportanceScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cluster:merge',
    description: 'Merge two duplicate StoryCluster entities (move PRs from source to target, delete source)',
)]
class ClusterMergeCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImportanceScoreCalculator $calculator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::REQUIRED, 'Target cluster ID (will be kept)')
            ->addArgument('source', InputArgument::REQUIRED, 'Source cluster ID (will be deleted after merge)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetId = (int) $input->getArgument('target');
        $sourceId = (int) $input->getArgument('source');

        if ($targetId === $sourceId) {
            $io->error('Target and source must be different clusters');
            return Command::FAILURE;
        }

        $target = $this->em->find(StoryCluster::class, $targetId);
        $source = $this->em->find(StoryCluster::class, $sourceId);

        if ($target === null) {
            $io->error(sprintf('Target cluster #%d not found', $targetId));
            return Command::FAILURE;
        }
        if ($source === null) {
            $io->error(sprintf('Source cluster #%d not found', $sourceId));
            return Command::FAILURE;
        }

        $io->section(sprintf('Merging cluster #%d → #%d', $sourceId, $targetId));
        $io->writeln(sprintf('  Target: "%s" (%d articles, %d sources)',
            mb_substr($target->getPrimaryHeadline(), 0, 60),
            $target->getArticleCount(),
            $target->getSourceCount(),
        ));
        $io->writeln(sprintf('  Source: "%s" (%d articles, %d sources)',
            mb_substr($source->getPrimaryHeadline(), 0, 60),
            $source->getArticleCount(),
            $source->getSourceCount(),
        ));

        try {
            $newScore = $this->em->wrapInTransaction(function () use ($target, $source) {
                // Move all PressReleases from source to target
                $moved = 0;
                foreach ($source->getPressReleases()->toArray() as $pr) {
                    if (!$target->getPressReleases()->contains($pr)) {
                        $target->addPressRelease($pr);
                        $moved++;
                    }
                    $source->removePressRelease($pr);
                }

                // Move topics
                foreach ($source->getTopics()->toArray() as $topic) {
                    if (!$target->getTopics()->contains($topic)) {
                        $target->addTopic($topic);
                    }
                    $source->removeTopic($topic);
                }

                // Recalculate counts
                $target->recalculateCounts();

                // Use earliest firstSeenAt
                if ($source->getFirstSeenAt() < $target->getFirstSeenAt()) {
                    $target->setFirstSeenAt($source->getFirstSeenAt());
                }
                $target->setLastUpdatedAt(new \DateTimeImmutable());

                // Merge region tags
                $targetTags = $target->getRegionTags() ?? [];
                $sourceTags = $source->getRegionTags() ?? [];
                $merged = array_values(array_unique(array_merge($targetTags, $sourceTags)));
                sort($merged);
                $target->setRegionTags($merged);

                // Delete source cluster
                $this->em->remove($source);
                $this->em->flush();

                // Re-score target
                $newScore = $this->calculator->calculate($target);
                $target->setImportanceScore($newScore);
                $this->em->flush();

                return $newScore;
            });

            $io->success(sprintf(
                'Merged: cluster #%d now has %d articles, %d sources, score=%.4f. Cluster #%d deleted.',
                $targetId,
                $target->getArticleCount(),
                $target->getSourceCount(),
                $newScore,
                $sourceId,
            ));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Cluster merge failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
