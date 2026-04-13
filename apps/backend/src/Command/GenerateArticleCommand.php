<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PressRelease;
use App\Entity\StoryCluster;
use App\Enum\ArticleStatus;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Repository\StoryClusterRepository;
use App\Service\Editorial\ArticleFactoryService;
use App\Service\Editorial\ArticleWriterService;
use App\Service\Editorial\PostApprovalDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-article',
    description: 'Generate an AI article from a StoryCluster using Gemini',
)]
class GenerateArticleCommand extends Command
{
    private const MIN_SCORE = 0.75;

    public function __construct(
        private readonly ArticleWriterService $writerService,
        private readonly StoryClusterRepository $clusterRepository,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('cluster-id', InputArgument::REQUIRED, 'StoryCluster ID')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview only, do not persist')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip eligibility checks')
            ->addOption('min-score', null, InputOption::VALUE_REQUIRED, 'Minimum importance score', (string) self::MIN_SCORE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $clusterId = (int) $input->getArgument('cluster-id');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');
        $minScore = (float) $input->getOption('min-score');

        $io->title('Generate Article from StoryCluster');

        // 1. Load cluster
        $cluster = $this->clusterRepository->find($clusterId);
        if ($cluster === null) {
            $io->error(sprintf('Cluster #%d not found', $clusterId));
            return Command::FAILURE;
        }

        $io->writeln(sprintf('Cluster #%d: "%s"', $clusterId, $cluster->getPrimaryHeadline()));
        $io->writeln(sprintf('Score: %.4f | Sources: %d | Articles: %d', $cluster->getImportanceScore(), $cluster->getSourceCount(), $cluster->getArticleCount()));

        // 2. Validate score
        if (!$force && $cluster->getImportanceScore() < $minScore) {
            $io->error(sprintf(
                'Cluster score %.4f is below minimum %.2f. Use --force to override.',
                $cluster->getImportanceScore(),
                $minScore,
            ));
            return Command::FAILURE;
        }

        // 3. Content-depth gate
        if (!$force) {
            $eligibility = $this->writerService->isEligibleForAiGeneration($cluster);
            if (!$eligibility['eligible']) {
                $io->error(sprintf(
                    'Content-depth gate failed: %s. Use --force to override.',
                    $eligibility['reason'],
                ));
                return Command::FAILURE;
            }
            $io->writeln(sprintf('Content-depth: %d sources, avg %d chars — OK', $eligibility['sourceCount'], $eligibility['avgLength']));
        }

        // 4. Generate article
        $io->writeln('');
        $io->writeln('Calling Gemini CLI...');

        $draft = $this->writerService->generateArticle($cluster);

        if ($draft === null) {
            $io->error('Article generation failed (Gemini error or invalid output)');
            return Command::FAILURE;
        }

        // 5. Display draft
        $io->section('Generated Draft');
        $io->writeln(sprintf('<info>Title:</info> %s', $draft->titleRo));
        $io->writeln(sprintf('<info>Lead:</info> %s', mb_substr($draft->leadRo, 0, 200)));
        $io->writeln(sprintf('<info>Content:</info> %d words', str_word_count($draft->contentRo)));
        $io->writeln(sprintf('<info>Meta:</info> %s', $draft->metaDescription));
        $io->writeln(sprintf('<info>Tags:</info> %s', implode(', ', $draft->suggestedTags)));
        $io->writeln(sprintf('<info>Confidence:</info> %.2f', $draft->confidenceScore));
        $io->writeln(sprintf('<info>Sources:</info> %d press releases', \count($draft->sourcePressReleaseIds)));

        if ($dryRun) {
            $io->note('DRY RUN — draft not persisted');
            return Command::SUCCESS;
        }

        // 6. Create PressRelease with AI-generated content (pending review)
        $pr = new PressRelease();
        $pr->setTitle(mb_substr($draft->titleRo, 0, 255));
        $pr->setLead($draft->leadRo);
        $pr->setContent($draft->contentRo);
        $pr->setStatus(PressReleaseStatus::PENDING);
        $pr->setSourceType(SourceType::AGGREGATOR);
        $pr->setSourceName(sprintf('AI:cluster#%d', $cluster->getId()));
        $pr->setContentHash(hash('sha256', 'ai_gen_' . $cluster->getId() . '_' . time()));
        $pr->setCategorySlug('externe');
        $pr->setDetectedLanguage('ro');

        $this->em->persist($pr);
        $this->em->flush();

        $io->success(sprintf(
            'PressRelease #%d created (status: pending). Approve via API to create Article.',
            $pr->getId(),
        ));

        return Command::SUCCESS;
    }
}
