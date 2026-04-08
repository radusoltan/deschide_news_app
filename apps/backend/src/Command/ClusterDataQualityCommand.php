<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PressRelease;
use App\Entity\Source;
use App\Entity\StoryCluster;
use App\Entity\Topic;
use App\Repository\SourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cluster:data-quality',
    description: 'Backfill detected_language, topic inheritance, and region tags on clusters',
)]
class ClusterDataQualityCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SourceRepository $sourceRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show changes without persisting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        $io->title('Cluster Data Quality Backfill');

        // Step 1: Backfill detected_language from original_language
        $languageCount = $this->backfillDetectedLanguage($io, $dryRun);

        // Step 2: Inherit topics from PR suggested_topics to clusters
        $topicCount = $this->inheritTopics($io, $dryRun);

        // Step 3: Populate region tags from Source.country
        $regionCount = $this->populateRegionTags($io, $dryRun);

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%s: languages=%d, topics=%d, regions=%d',
            $dryRun ? 'DRY RUN' : 'Done',
            $languageCount,
            $topicCount,
            $regionCount,
        ));

        return Command::SUCCESS;
    }

    private function backfillDetectedLanguage(SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Backfill detected_language');

        // Copy original_language to detected_language where null
        $count = 0;
        $prs = $this->em->createQuery(
            'SELECT pr FROM App\Entity\PressRelease pr WHERE pr.detectedLanguage IS NULL AND pr.originalLanguage IS NOT NULL'
        )->toIterable();

        foreach ($prs as $pr) {
            if (!$dryRun) {
                $pr->setDetectedLanguage($pr->getOriginalLanguage());
            }
            $count++;

            if ($count % 100 === 0) {
                if (!$dryRun) {
                    $this->em->flush();
                }
                $this->em->clear(PressRelease::class);
            }
        }

        $io->writeln(sprintf('  Set detected_language on %d PressReleases', $count));
        return $count;
    }

    private function inheritTopics(SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Inherit topics from PressReleases to clusters');

        $topicRepo = $this->em->getRepository(Topic::class);
        $clusters = $this->em->getRepository(StoryCluster::class)->findAll();
        $linked = 0;

        foreach ($clusters as $cluster) {
            $topicSlugs = [];
            foreach ($cluster->getPressReleases() as $pr) {
                $suggested = $pr->getSuggestedTopics();
                if ($suggested !== null) {
                    foreach ($suggested as $topicData) {
                        $slug = \is_array($topicData) ? ($topicData['slug'] ?? null) : (string) $topicData;
                        if ($slug !== null) {
                            $topicSlugs[$slug] = true;
                        }
                    }
                }
            }

            if ($topicSlugs === []) {
                continue;
            }

            foreach (array_keys($topicSlugs) as $slug) {
                $topic = $topicRepo->findOneBy(['slug' => $slug]);
                if ($topic !== null && !$cluster->getTopics()->contains($topic)) {
                    if (!$dryRun) {
                        $cluster->addTopic($topic);
                    }
                    $linked++;
                }
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->writeln(sprintf('  Linked %d topic-cluster associations', $linked));
        return $linked;
    }

    private function populateRegionTags(SymfonyStyle $io, bool $dryRun): int
    {
        $io->section('Populate region tags from Source.country');

        $clusters = $this->em->getRepository(StoryCluster::class)->findAll();
        $updated = 0;

        foreach ($clusters as $cluster) {
            $countries = [];
            foreach ($cluster->getPressReleases() as $pr) {
                // Prefer Source FK
                $source = $pr->getSource();
                if ($source !== null && $source->getCountry() !== null) {
                    $countries[$source->getCountry()] = true;
                    continue;
                }

                // Fallback: hostname lookup
                $hostname = $pr->getSourceHostname();
                if ($hostname !== null) {
                    $source = $this->sourceRepository->findByDomain($hostname);
                    if ($source !== null && $source->getCountry() !== null) {
                        $countries[$source->getCountry()] = true;
                    }
                }
            }

            $countryCodes = array_keys($countries);
            sort($countryCodes);

            if ($countryCodes !== [] && $countryCodes !== ($cluster->getRegionTags() ?? [])) {
                if (!$dryRun) {
                    $cluster->setRegionTags($countryCodes);
                }
                $updated++;
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->writeln(sprintf('  Updated region tags on %d clusters', $updated));
        return $updated;
    }
}
