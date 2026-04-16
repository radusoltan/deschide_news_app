<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Entity\TopicBriefing;
use App\Enum\BriefingCadence;
use App\Enum\BriefingStatus;
use App\Repository\AppSettingRepository;
use App\Service\Ai\AnthropicClientInterface;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates topic briefings using a dual-LLM chain: Gemini draft → Claude polish.
 *
 * Pattern: same as ClusterSummaryService with 64KB safety:
 * - Max 15 PRs per prompt, 500 chars each
 * - Warn if prompt exceeds 50KB
 *
 * Fallback (ADR-016 D5):
 * - Gemini draft = baseline. If fails → return null, no briefing.
 * - Claude polish = upgrade. If fails → persist Gemini draft with claude_polished=false.
 */
class TopicBriefingWriterService
{
    private const MAX_PRS_IN_PROMPT = 15;
    private const MAX_CONTENT_PER_PR = 500;
    private const PROMPT_SIZE_WARNING_BYTES = 50_000;

    public function __construct(
        private readonly GeminiCliService $geminiCli,
        private readonly AnthropicClientInterface $claudeCli,
        private readonly AppSettingRepository $settings,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Generate a briefing for a topic in the given cadence and date range.
     *
     * Returns null if Gemini draft fails (no briefing created).
     */
    public function generate(Topic $topic, BriefingCadence $cadence, DateRange $range): ?TopicBriefing
    {
        $briefing = new TopicBriefing($topic, $cadence, $range->from, $range->to);
        $briefing->setStatus(BriefingStatus::GENERATING);

        $this->em->persist($briefing);
        $this->em->flush();

        // 1. Query PRs for context
        $pressReleases = $this->findPressReleases($topic, $range);
        $briefing->setPrCount(\count($pressReleases));

        if ($pressReleases === []) {
            $briefing->setStatus(BriefingStatus::FAILED);
            $this->em->flush();
            $this->logger->info('TopicBriefingWriter: no PRs for topic', [
                'topicId' => $topic->getId(),
                'cadence' => $cadence->value,
            ]);

            return null;
        }

        // 2. Gemini draft (baseline)
        $prompt = $this->buildGeminiPrompt($topic, $cadence, $pressReleases);
        $geminiTimeout = $this->settings->getInt('briefing.llm.gemini_timeout', 120);
        $rawDraft = $this->callGemini($prompt, $geminiTimeout);

        if ($rawDraft === null) {
            $briefing->setStatus(BriefingStatus::FAILED);
            $this->em->flush();

            return null;
        }

        $parsed = $this->parseJsonResponse($rawDraft);
        if ($parsed === null) {
            $briefing->setStatus(BriefingStatus::FAILED);
            $briefing->setGeminiDraftRaw($rawDraft);
            $this->em->flush();
            $this->logger->warning('TopicBriefingWriter: failed to parse Gemini JSON', [
                'topicId' => $topic->getId(),
                'rawLength' => \strlen($rawDraft),
            ]);

            return null;
        }

        // Apply Gemini draft to briefing
        $this->applyParsedData($briefing, $parsed);
        $briefing->setGeminiDraftRaw($rawDraft);
        $briefing->setStatus(BriefingStatus::DRAFT);
        $briefing->setGeneratedAt(new \DateTimeImmutable());

        // 3. Claude polish (upgrade, optional)
        $polishEnabled = $this->settings->getBool('briefing.llm.polish_enabled', true);

        if ($polishEnabled) {
            $polished = $this->polishWithClaude($briefing);
            if ($polished) {
                $briefing->setClaudePolished(true);
                $briefing->setStatus(BriefingStatus::POLISHED);
            }
            // On Claude failure: Gemini draft persists with claude_polished=false
        }

        $this->em->flush();

        $this->logger->info('TopicBriefingWriter: generated briefing', [
            'topicId' => $topic->getId(),
            'cadence' => $cadence->value,
            'briefingId' => $briefing->getId(),
            'prCount' => $briefing->getPrCount(),
            'claudePolished' => $briefing->isClaudePolished(),
        ]);

        return $briefing;
    }

    /**
     * @param list<PressRelease> $pressReleases
     */
    private function buildGeminiPrompt(Topic $topic, BriefingCadence $cadence, array $pressReleases): string
    {
        $topicTitle = $topic->getTitle();
        $cadenceLabel = $cadence->value;
        $prCount = \count($pressReleases);

        $articles = '';
        $count = 0;
        foreach ($pressReleases as $pr) {
            if ($count >= self::MAX_PRS_IN_PROMPT) {
                break;
            }

            $title = $pr->getTitle();
            $source = $pr->getSource()?->getName() ?? $pr->getSourceName() ?? 'Unknown';
            $content = mb_substr(strip_tags($pr->getContent()), 0, self::MAX_CONTENT_PER_PR);
            $date = $pr->getReceivedAt()->format('Y-m-d H:i');

            $articles .= <<<PR

            --- PR {$count} ({$source}, {$date}) ---
            Title: {$title}
            Content: {$content}
            PR;

            $count++;
        }

        $prompt = <<<PROMPT
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

        $promptBytes = \strlen($prompt);
        if ($promptBytes > self::PROMPT_SIZE_WARNING_BYTES) {
            $this->logger->warning('TopicBriefingWriter: prompt exceeds 50KB', [
                'topicId' => $topic->getId(),
                'promptBytes' => $promptBytes,
                'prCount' => $prCount,
            ]);
        }

        return $prompt;
    }

    private function callGemini(string $prompt, int $timeout): ?string
    {
        try {
            return $this->geminiCli->execute($prompt, ['timeout' => $timeout]);
        } catch (GeminiCliException $e) {
            $this->logger->error('TopicBriefingWriter: Gemini failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function polishWithClaude(TopicBriefing $briefing): bool
    {
        $claudeTimeout = $this->settings->getInt('briefing.llm.claude_timeout', 120);

        $prompt = $this->buildClaudePolishPrompt($briefing);

        try {
            $result = $this->claudeCli->chat(
                [['role' => 'user', 'content' => $prompt]],
                'claude-sonnet-4-6',
                'You are a senior Moldovan news editor. Polish the editorial briefing for journalistic quality.',
            );

            $parsed = $this->parseJsonResponse($result);
            if ($parsed === null) {
                $this->logger->warning('TopicBriefingWriter: Claude polish response not parseable', [
                    'briefingId' => $briefing->getId(),
                    'rawLength' => \strlen($result),
                ]);

                return false;
            }

            $this->applyParsedData($briefing, $parsed);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('TopicBriefingWriter: Claude polish failed, keeping Gemini draft', [
                'briefingId' => $briefing->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function buildClaudePolishPrompt(TopicBriefing $briefing): string
    {
        $title = $briefing->getTitle() ?? '';
        $short = $briefing->getSummaryShort() ?? '';
        $long = $briefing->getSummaryLong() ?? '';
        $why = $briefing->getWhyItMatters() ?? '';
        $facts = json_encode($briefing->getKeyFacts() ?? [], \JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
        Polish the following editorial briefing for Deschide.md. Improve journalistic tone,
        clarify facts, fix any grammatical issues. Keep Romanian with comma-below diacritics (ș, ț).

        Current content:
        - Title: {$title}
        - Summary short: {$short}
        - Summary long: {$long}
        - Why it matters: {$why}
        - Key facts: {$facts}

        Return the polished version as JSON with exactly these fields:
        {
          "title": "...",
          "summary_short": "...",
          "summary_long": "...",
          "why_it_matters": "...",
          "key_facts": ["...", "..."]
        }

        Rules:
        - Return ONLY valid JSON, no markdown code blocks
        - Keep all facts from the original — do not add or remove information
        - Use comma-below diacritics exclusively (ș, ț)
        PROMPT;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function applyParsedData(TopicBriefing $briefing, array $data): void
    {
        if (isset($data['title']) && \is_string($data['title'])) {
            $briefing->setTitle($data['title']);
        }
        if (isset($data['summary_short']) && \is_string($data['summary_short'])) {
            $briefing->setSummaryShort($data['summary_short']);
        }
        if (isset($data['summary_long']) && \is_string($data['summary_long'])) {
            $briefing->setSummaryLong($data['summary_long']);
        }
        if (isset($data['why_it_matters']) && \is_string($data['why_it_matters'])) {
            $briefing->setWhyItMatters($data['why_it_matters']);
        }
        if (isset($data['key_facts']) && \is_array($data['key_facts'])) {
            $briefing->setKeyFacts($data['key_facts']);
        }
    }

    /**
     * Parse JSON response, stripping markdown code blocks if present.
     *
     * @return array<string, mixed>|null
     */
    private function parseJsonResponse(string $raw): ?array
    {
        $json = $raw;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $json, $m)) {
            $json = $m[1];
        }

        $json = trim($json);
        $data = json_decode($json, true);

        if (!\is_array($data)) {
            return null;
        }

        // Must have at least summary_short
        if (!isset($data['summary_short']) || !\is_string($data['summary_short'])) {
            return null;
        }

        return $data;
    }

    /**
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
            ->setMaxResults(self::MAX_PRS_IN_PROMPT)
            ->getQuery()
            ->getResult();
    }
}
