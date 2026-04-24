<?php

declare(strict_types=1);

namespace App\Command\Topic;

use App\Agent\AgentDispatcher;
use App\Dto\Agent\AgentRequest;
use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Enum\BriefingCadence;
use App\Enum\LlmModelTier;
use App\Enum\TopicStatus;
use App\Message\Topic\TriggerTopicBriefingRunMessage;
use App\Service\Editorial\BriefingEligibilityGateService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:briefing:trigger',
    description: 'Dispatch TriggerTopicBriefingRunMessage for a cadence; with --dry-run, measure Sonnet draft latency without persisting briefings.',
)]
final class TriggerBriefingRunCommand extends Command
{
    // Mirror private constants from TopicBriefingWriterService for the dry-run
    // prompt. Kept inline (not refactored to shared helper) because this path
    // is diagnostic-only and must stay decoupled from the production service
    // while T57.P4+P5 migration is still in flight.
    private const int DRY_RUN_MAX_PRS_IN_PROMPT = 15;
    private const int DRY_RUN_MAX_CONTENT_PER_PR = 500;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
        private readonly BriefingEligibilityGateService $gate,
        private readonly AgentDispatcher $dispatcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('cadence', InputArgument::OPTIONAL, 'hourly | daily | weekly', 'hourly');
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Measure Sonnet draft latency per topic. No message dispatch, no TopicBriefing persist. T57.P4 D-2 latency protocol.',
        );
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_REQUIRED,
            'Max eligible topics to dispatch under --dry-run (default 3).',
            '3',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $cadence = BriefingCadence::from((string) $input->getArgument('cadence'));

        if ((bool) $input->getOption('dry-run')) {
            return $this->executeDryRun($io, $cadence, (int) $input->getOption('limit'));
        }

        $this->bus->dispatch(new TriggerTopicBriefingRunMessage($cadence));
        $output->writeln(sprintf('Dispatched TriggerTopicBriefingRunMessage(%s)', $cadence->value));

        return Command::SUCCESS;
    }

    /**
     * T57.P4 D-2 latency measurement. Executes the briefing draft path
     * synchronously against Sonnet via AgentDispatcher for every eligible
     * topic up to --limit; skips persistence. Emits a per-topic timing table
     * and p50/p95 aggregate so the operator can evaluate the 22:00 deadline
     * margin before committing to the migration.
     */
    private function executeDryRun(SymfonyStyle $io, BriefingCadence $cadence, int $limit): int
    {
        if ($cadence === BriefingCadence::HOURLY) {
            $io->error('--dry-run measures Sonnet draft latency (daily/weekly cadence per ADR-024 D1). Hourly cadence uses Haiku — out of scope for D-2.');

            return Command::INVALID;
        }

        $io->title(sprintf('Briefing Sonnet dry-run — cadence=%s, limit=%d', $cadence->value, $limit));

        $range = DateRange::forCadence($cadence);
        $topics = $this->em->getRepository(Topic::class)->findBy([
            'isActive' => true,
            'status' => TopicStatus::ACTIVE,
        ]);

        $agentId = $cadence === BriefingCadence::DAILY
            ? 'briefing_daily'
            : 'briefing_weekly';

        $samples = [];
        $processed = 0;

        foreach ($topics as $topic) {
            if ($processed >= $limit) {
                break;
            }

            $decision = $this->gate->evaluate($topic, $cadence, $range);
            if (!$decision->eligible) {
                continue;
            }

            $pressReleases = $this->findPressReleases($topic, $range);
            if ($pressReleases === []) {
                continue;
            }

            $prompt = $this->buildDryRunPrompt($topic, $cadence, $pressReleases);

            try {
                $start = microtime(true);
                $response = $this->dispatcher->dispatch(new AgentRequest(
                    agentId: $agentId,
                    messages: [['role' => 'user', 'content' => $prompt]],
                    tier: LlmModelTier::SONNET,
                ));
                $wallMs = (int) round((microtime(true) - $start) * 1000);

                $apiMs = isset($response->metrics['duration_api_ms'])
                    ? (int) $response->metrics['duration_api_ms']
                    : null;
                $executorMs = isset($response->metrics['duration_ms'])
                    ? (int) $response->metrics['duration_ms']
                    : null;

                $samples[] = [
                    'topic_id' => $topic->getId(),
                    'topic_title' => $topic->getTitle(),
                    'pr_count' => \count($pressReleases),
                    'wall_ms' => $wallMs,
                    'executor_ms' => $executorMs,
                    'api_ms' => $apiMs,
                    'attempts' => $response->attempts,
                    'content_bytes' => \strlen($response->content),
                ];
            } catch (\Throwable $e) {
                $samples[] = [
                    'topic_id' => $topic->getId(),
                    'topic_title' => $topic->getTitle(),
                    'pr_count' => \count($pressReleases),
                    'wall_ms' => null,
                    'executor_ms' => null,
                    'api_ms' => null,
                    'attempts' => null,
                    'content_bytes' => 0,
                    'error' => $e->getMessage(),
                ];
            }

            $processed++;
        }

        if ($samples === []) {
            $io->warning('No eligible topics found for measurement. Seed dev fixtures or lower thresholds before retrying.');

            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($samples as $s) {
            $rows[] = [
                $s['topic_id'],
                mb_substr((string) $s['topic_title'], 0, 40),
                $s['pr_count'],
                $s['wall_ms'] ?? 'FAIL',
                $s['executor_ms'] ?? '—',
                $s['api_ms'] ?? '—',
                $s['attempts'] ?? '—',
                $s['content_bytes'],
                $s['error'] ?? '',
            ];
        }

        $io->table(
            ['topic_id', 'title', 'pr_count', 'wall_ms', 'executor_ms', 'api_ms', 'attempts', 'bytes', 'error'],
            $rows,
        );

        $successful = array_values(array_filter(
            $samples,
            static fn (array $s): bool => $s['wall_ms'] !== null,
        ));

        if ($successful === []) {
            $io->error('All dispatches failed. See error column above.');

            return Command::FAILURE;
        }

        $walls = array_column($successful, 'wall_ms');
        sort($walls);
        $n = \count($walls);
        $p50 = $walls[(int) floor(($n - 1) * 0.5)];
        $p95 = $walls[(int) floor(($n - 1) * 0.95)];
        $min = $walls[0];
        $max = $walls[$n - 1];
        $mean = (int) round(array_sum($walls) / $n);

        $io->section('Aggregate (wall-clock ms, successful samples)');
        $io->listing([
            sprintf('n=%d', $n),
            sprintf('min=%d', $min),
            sprintf('p50=%d', $p50),
            sprintf('p95=%d', $p95),
            sprintf('max=%d', $max),
            sprintf('mean=%d', $mean),
        ]);

        $io->note(sprintf(
            'D-2 trigger criterion: p95 × typical-topic-count ≥ 15 min (900_000 ms). Current: p95=%d × n=%d ≈ %d ms.',
            $p95,
            $n,
            $p95 * $n,
        ));

        return Command::SUCCESS;
    }

    /**
     * Duplicate of {@see \App\Service\Editorial\TopicBriefingWriterService::findPressReleases()},
     * intentionally kept inline in the diagnostic command to avoid touching the
     * production service surface while T57.P4+P5 migration is in flight. After
     * P4+P5 merges, this duplication becomes cleanable debt for T57.P9.
     *
     * @return list<PressRelease>
     */
    private function findPressReleases(Topic $topic, DateRange $range): array
    {
        return $this->em->createQueryBuilder()
            ->select('pr')
            ->from(PressRelease::class, 'pr')
            ->join('pr.pressReleaseTopics', 'prt')
            ->where('prt.topic = :topic')
            ->andWhere('pr.receivedAt >= :from')
            ->andWhere('pr.receivedAt <= :to')
            ->setParameter('topic', $topic)
            ->setParameter('from', $range->from)
            ->setParameter('to', $range->to)
            ->orderBy('pr.receivedAt', 'DESC')
            ->setMaxResults(self::DRY_RUN_MAX_PRS_IN_PROMPT)
            ->getQuery()
            ->getResult();
    }

    /**
     * Duplicate of {@see \App\Service\Editorial\TopicBriefingWriterService::buildGeminiPrompt()}
     * for diagnostic parity. See rationale on {@see self::findPressReleases()}.
     *
     * @param list<PressRelease> $pressReleases
     */
    private function buildDryRunPrompt(Topic $topic, BriefingCadence $cadence, array $pressReleases): string
    {
        $topicTitle = $topic->getTitle();
        $cadenceLabel = $cadence->value;
        $prCount = \count($pressReleases);

        $articles = '';
        $count = 0;
        foreach ($pressReleases as $pr) {
            if ($count >= self::DRY_RUN_MAX_PRS_IN_PROMPT) {
                break;
            }

            $title = $pr->getTitle();
            $source = $pr->getSource()?->getName() ?? $pr->getSourceName() ?? 'Unknown';
            $content = mb_substr(strip_tags($pr->getContent()), 0, self::DRY_RUN_MAX_CONTENT_PER_PR);
            $date = $pr->getReceivedAt()->format('Y-m-d H:i');

            $articles .= <<<PR

            --- PR {$count} ({$source}, {$date}) ---
            Title: {$title}
            Content: {$content}
            PR;

            $count++;
        }

        return <<<PROMPT
        You are a senior news editor at Deschide.md, a Moldovan news portal.

        Generate a {$cadenceLabel} editorial briefing for the topic "{$topicTitle}" based on {$prCount} press releases.

        Press releases:
        {$articles}

        Generate a JSON summary with these fields:

        {
          "title": "Briefing title in Romanian (catchy, informative, max 100 chars). Use comma-below diacritics (ș, ț).",
          "summary_short": "1-2 sentences, TL;DR in Romanian. Use comma-below diacritics (ș, ț).",
          "summary_long": "3-6 sentences, comprehensive overview in Romanian with context. Use comma-below diacritics.",
          "why_it_matters": "2-3 sentences explaining relevance for Moldova/region. Romanian, comma-below.",
          "key_facts": ["fact 1 in Romanian", "fact 2", "fact 3", "fact 4", "fact 5"]
        }

        Rules:
        - Write in Romanian with comma-below diacritics exclusively (ș, ț NOT ş, ţ)
        - Base ALL facts strictly on the provided press releases. DO NOT invent information.
        - key_facts: 3-5 bullet points, each 1 sentence
        - summary_short: max 50 words
        - summary_long: max 200 words
        - why_it_matters: focus on Republic of Moldova, EU integration, regional impact
        - Return ONLY valid JSON, no markdown code blocks
        PROMPT;
    }
}
