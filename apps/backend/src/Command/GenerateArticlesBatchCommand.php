<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\PressRelease;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Repository\StoryClusterRepository;
use App\Service\Editorial\ArticleWriterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-articles',
    description: 'Batch-generate AI articles from eligible StoryCluster entities',
)]
class GenerateArticlesBatchCommand extends Command
{
    private const DEFAULT_MIN_SCORE = 0.80;
    private const DEFAULT_LIMIT = 5;
    private const DELAY_BETWEEN_CALLS = 5;

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
            ->addOption('min-score', null, InputOption::VALUE_REQUIRED, 'Minimum importance score', (string) self::DEFAULT_MIN_SCORE)
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max articles to generate', (string) self::DEFAULT_LIMIT)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List candidates without generating');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $minScore = (float) $input->getOption('min-score');
        $limit = (int) $input->getOption('limit');
        $dryRun = $input->getOption('dry-run');

        $io->title('Batch Article Generation');
        $io->writeln(sprintf('Min score: %.2f | Limit: %d%s', $minScore, $limit, $dryRun ? ' | DRY RUN' : ''));

        // Find eligible clusters
        $clusters = $this->clusterRepository->createQueryBuilder('c')
            ->where('c.importanceScore >= :minScore')
            ->andWhere('c.summaryShort IS NOT NULL')
            ->setParameter('minScore', $minScore)
            ->orderBy('c.importanceScore', 'DESC')
            ->setMaxResults($limit * 2) // Fetch more to account for ineligible ones
            ->getQuery()
            ->getResult();

        // Filter by content-depth gate
        $candidates = [];
        foreach ($clusters as $cluster) {
            $eligibility = $this->writerService->isEligibleForAiGeneration($cluster);
            if ($eligibility['eligible']) {
                $candidates[] = ['cluster' => $cluster, 'eligibility' => $eligibility];
            }
            if (\count($candidates) >= $limit) {
                break;
            }
        }

        if ($candidates === []) {
            $io->info('No eligible clusters found');
            return Command::SUCCESS;
        }

        // Display candidates
        $io->section(sprintf('%d Eligible Cluster(s)', \count($candidates)));
        foreach ($candidates as $c) {
            $cluster = $c['cluster'];
            $io->writeln(sprintf(
                '  #%d (score=%.4f, %d sources, avg %d chars) "%s"',
                $cluster->getId(),
                $cluster->getImportanceScore(),
                $c['eligibility']['sourceCount'],
                $c['eligibility']['avgLength'],
                mb_substr($cluster->getPrimaryHeadline(), 0, 60),
            ));
        }

        if ($dryRun) {
            $io->note('DRY RUN — no articles generated');
            return Command::SUCCESS;
        }

        // Generate articles
        $generated = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($candidates as $idx => $c) {
            $cluster = $c['cluster'];
            $io->writeln('');
            $io->write(sprintf('  [%d/%d] #%d "%.50s" ... ', $idx + 1, \count($candidates), $cluster->getId(), $cluster->getPrimaryHeadline()));

            $draft = $this->writerService->generateArticle($cluster);

            if ($draft === null) {
                $io->writeln('<error>FAILED</error>');
                $failed++;
                continue;
            }

            // Create PressRelease
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

            $io->writeln(sprintf('<info>OK</info> → PR#%d (confidence: %.2f, %d words)', $pr->getId(), $draft->confidenceScore, str_word_count($draft->contentRo)));
            $generated++;

            // Rate limiting between calls
            if ($idx < \count($candidates) - 1) {
                sleep(self::DELAY_BETWEEN_CALLS);
            }
        }

        $io->writeln('');
        $io->section('Summary');
        $io->writeln(sprintf('  Generated: %d', $generated));
        $io->writeln(sprintf('  Failed: %d', $failed));
        $io->writeln(sprintf('  Skipped: %d', $skipped));

        if ($failed > 0) {
            $io->warning(sprintf('%d generation(s) failed. Check logs for details.', $failed));
        } else {
            $io->success(sprintf('%d article(s) generated successfully', $generated));
        }

        return $failed > 0 && $generated === 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
