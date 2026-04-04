<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use App\Entity\Topic;
use App\Service\Editorial\DossierGenerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:editorial:generate-dossiers',
    description: 'Generate narrative dossiers for topics with enough recent articles',
)]
final class GenerateDossiersCommand extends Command
{
    public function __construct(
        private readonly DossierGenerationService $dossierService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('topic', null, InputOption::VALUE_REQUIRED, 'Specific topic name')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Process all topics above threshold')
            ->addOption('threshold', 't', InputOption::VALUE_REQUIRED, 'Minimum articles for dossier', '5')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Look back N days for articles', '30')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $threshold = (int) $input->getOption('threshold');
        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');
        $specificTopic = $input->getOption('topic');

        $io->title('Dossier Generation');

        if ($specificTopic !== null) {
            $io->info("Processing specific topic: {$specificTopic}");
            $topicNames = [$specificTopic];
        } elseif ($input->getOption('all')) {
            $topics = $this->em->getRepository(Topic::class)->findAll();
            $topicNames = array_map(fn (Topic $t) => $t->getName(), $topics);
            $io->info(\count($topicNames) . ' topics found');
        } else {
            $io->error('Provide --topic=NAME or --all');

            return Command::FAILURE;
        }

        if ($topicNames === []) {
            $io->note('No topics found');

            return Command::SUCCESS;
        }

        $generated = 0;

        foreach ($topicNames as $topicName) {
            $io->section("Generating dossier: {$topicName}");

            $articles = $this->dossierService->getRecentArticlesForTopic($topicName, $days);
            $io->info(\count($articles) . ' articles found in DB');

            if (\count($articles) < $threshold) {
                $io->note("Only {$articles} articles (threshold: {$threshold}), skipping");

                continue;
            }

            if ($articles === []) {
                $io->note('No articles found for this topic, skipping');

                continue;
            }

            if ($dryRun) {
                $io->note('Dry run — would generate dossier with ' . \count($articles) . ' articles');

                continue;
            }

            $gc = $this->dossierService->generateDossier($topicName, $articles);

            if ($gc !== null) {
                $io->success("Dossier saved as GeneratedContent #{$gc->getId()}");
                $generated++;
            } else {
                $io->warning("Failed to generate dossier for {$topicName}");
            }
        }

        $io->success("Done: {$generated} dossier(s) generated");

        return Command::SUCCESS;
    }
}
