<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Entity\TopicBriefing;
use App\Enum\BriefingCadence;
use App\Enum\BriefingStatus;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\AnthropicClientInterface;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Ai\TierResolver;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates topic briefings via the editorial LLM pipeline.
 *
 * T57.P4+P5 — migrated to {@see AgentDispatcher} with cadence-branched tier
 * resolution (ADR-024 D1):
 *
 * - **DAILY** → Sonnet draft. No polish chain. No fallback (status=FAILED on
 *   dispatcher exhaustion). 100% LlmAgentCallLog coverage via dispatcher.
 * - **HOURLY** → Haiku draft → Sonnet polish as a SECOND dispatcher call
 *   under `briefing_hourly_polish` agent id (preserves ADR-024 D2 100%
 *   coverage invariant). Gemini CLI fallback retained inline until T57.P8
 *   retires the downgrade-only policy. Polish failure is NON-FATAL: briefing
 *   persists as DRAFT with Haiku content.
 * - **WEEKLY** → Sonnet draft. No polish. No fallback. Same contract as DAILY.
 *
 * Halt propagation: {@see EmergencyHaltException} thrown by the dispatcher's
 * pre-LLM `editorial.emergency_halt` circuit breaker is rethrown past the
 * Gemini fallback catch (HOURLY) so halts do NOT silently bypass the circuit
 * breaker. Halt during DRAFT dispatch → status=FAILED. Halt during POLISH
 * dispatch → status=DRAFT preserved (polish is best-effort).
 *
 * Legacy rollback: `briefing.llm.use_legacy_gemini_{daily,hourly,weekly}`
 * AppSettings flags (critical per {@see \App\Entity\AppSetting::CRITICAL_KEYS},
 * `--reason` mandatory on flip) route the respective cadence back through the
 * pre-migration Gemini draft + Claude polish path without a code revert. The
 * legacy path emits NO LlmAgentCallLog rows — intentional, since the whole
 * point of the rollback is to return to pre-P4+P5 observability.
 *
 * Dual-LLM 64KB safety preserved:
 * - Max 15 PRs per prompt, 500 chars each
 * - Warn if prompt exceeds 50KB
 */
class TopicBriefingWriterService
{
    public const AGENT_ID_DAILY = 'briefing_daily';
    public const AGENT_ID_HOURLY = 'briefing_hourly';
    public const AGENT_ID_WEEKLY = 'briefing_weekly';
    public const AGENT_ID_HOURLY_POLISH = 'briefing_hourly_polish';

    private const MAX_PRS_IN_PROMPT = 15;
    private const MAX_CONTENT_PER_PR = 500;
    private const PROMPT_SIZE_WARNING_BYTES = 50_000;

    public function __construct(
        private readonly AgentDispatcher $dispatcher,
        private readonly TierResolver $tierResolver,
        private readonly GeminiCliService $geminiCli,
        private readonly AnthropicClientInterface $claudeCli,
        private readonly AppSettingRepository $settings,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Generate a briefing for a topic in the given cadence and date range.
     *
     * Returns null on pre-LLM gating failure (no PRs). Returns the briefing
     * entity on both success and post-LLM failure paths — caller distinguishes
     * via {@see TopicBriefing::getStatus()}.
     */
    public function generate(Topic $topic, BriefingCadence $cadence, DateRange $range): ?TopicBriefing
    {
        $briefing = new TopicBriefing($topic, $cadence, $range->from, $range->to);
        $briefing->setStatus(BriefingStatus::GENERATING);

        $this->em->persist($briefing);
        $this->em->flush();

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

        if ($this->shouldUseLegacy($cadence)) {
            return $this->generateViaLegacyPath($briefing, $topic, $cadence, $pressReleases);
        }

        return $this->generateViaDispatcherPath($briefing, $topic, $cadence, $pressReleases);
    }

    /**
     * Post-T57.P4+P5 main path — draft via AgentDispatcher, optional polish via
     * a second dispatcher call for HOURLY only.
     *
     * @param list<PressRelease> $pressReleases
     */
    private function generateViaDispatcherPath(
        TopicBriefing $briefing,
        Topic $topic,
        BriefingCadence $cadence,
        array $pressReleases,
    ): ?TopicBriefing {
        $prompt = $this->buildDraftPrompt($topic, $cadence, $pressReleases);
        $agentId = $this->resolveAgentId($cadence);

        try {
            $rawDraft = $this->dispatchDraft($agentId, $prompt, $cadence);
        } catch (\RuntimeException $e) {
            // Emergency halt or non-recoverable dispatcher+fallback failure.
            $briefing->setStatus(BriefingStatus::FAILED);
            $this->em->flush();
            $this->logger->warning('TopicBriefingWriter: draft exhausted, marking FAILED', [
                'topicId' => $topic->getId(),
                'cadence' => $cadence->value,
                'reason' => $e->getMessage(),
            ]);

            return null;
        }

        $parsed = $this->parseJsonResponse($rawDraft);
        if ($parsed === null) {
            $briefing->setStatus(BriefingStatus::FAILED);
            $briefing->setGeminiDraftRaw($rawDraft);
            $this->em->flush();
            $this->logger->warning('TopicBriefingWriter: failed to parse draft JSON', [
                'topicId' => $topic->getId(),
                'cadence' => $cadence->value,
                'rawLength' => \strlen($rawDraft),
            ]);

            return null;
        }

        $this->applyParsedData($briefing, $parsed);
        $briefing->setGeminiDraftRaw($rawDraft);
        $briefing->setStatus(BriefingStatus::DRAFT);
        $briefing->setGeneratedAt(new \DateTimeImmutable());

        if ($this->shouldPolish($cadence)) {
            $polished = $this->dispatchPolish($briefing);
            if ($polished) {
                $briefing->setClaudePolished(true);
                $briefing->setStatus(BriefingStatus::POLISHED);
            }
            // On polish failure (including halt): DRAFT status preserved,
            // briefing remains usable. Polish is best-effort per AC#10.
        }

        $this->em->flush();

        $this->logger->info('TopicBriefingWriter: generated briefing', [
            'topicId' => $topic->getId(),
            'cadence' => $cadence->value,
            'briefingId' => $briefing->getId(),
            'prCount' => $briefing->getPrCount(),
            'claudePolished' => $briefing->isClaudePolished(),
            'path' => 'dispatcher',
        ]);

        return $briefing;
    }

    /**
     * Dispatch the draft call. On emergency halt, rethrow so the caller marks
     * FAILED without silently routing through fallback. On generic dispatcher
     * failure: HOURLY cadence retries via Gemini inline (retained until
     * T57.P8); DAILY/WEEKLY propagate failure upward (no fallback per ADR-024
     * D1 — Sonnet → Gemini would be a silent quality downgrade).
     *
     * @throws \RuntimeException with message `emergency_halt` on halt, or the
     *         upstream error message on exhausted fallback.
     */
    private function dispatchDraft(string $agentId, string $prompt, BriefingCadence $cadence): string
    {
        try {
            $tier = $this->tierResolver->resolve($agentId);
            $response = $this->dispatcher->dispatch(new AgentRequest(
                agentId: $agentId,
                messages: [['role' => 'user', 'content' => $prompt]],
                tier: $tier,
            ));

            return $response->content;
        } catch (EmergencyHaltException) {
            throw new \RuntimeException('emergency_halt');
        } catch (\Throwable $e) {
            if (!$this->shouldFallback($cadence)) {
                throw new \RuntimeException(
                    'draft dispatcher failed (no fallback for cadence): ' . $e->getMessage(),
                    0,
                    $e,
                );
            }

            $this->logger->warning('TopicBriefingWriter: draft dispatcher failed, falling back to Gemini', [
                'agentId' => $agentId,
                'cadence' => $cadence->value,
                'error' => $e->getMessage(),
            ]);

            try {
                $geminiTimeout = $this->settings->getInt('briefing.llm.gemini_timeout', 120);

                return $this->geminiCli->execute($prompt, ['timeout' => $geminiTimeout]);
            } catch (GeminiCliException $geminiError) {
                throw new \RuntimeException(
                    'draft dispatcher + Gemini fallback both failed: ' . $geminiError->getMessage(),
                    0,
                    $geminiError,
                );
            }
        }
    }

    /**
     * HOURLY polish step routed through the dispatcher under its own agent id
     * to preserve ADR-024 D2 100% LlmAgentCallLog coverage. Failures are
     * non-fatal — the caller keeps the Haiku DRAFT.
     */
    private function dispatchPolish(TopicBriefing $briefing): bool
    {
        $prompt = $this->buildClaudePolishPrompt($briefing);

        try {
            $tier = $this->tierResolver->resolve(self::AGENT_ID_HOURLY_POLISH);
            $response = $this->dispatcher->dispatch(new AgentRequest(
                agentId: self::AGENT_ID_HOURLY_POLISH,
                messages: [['role' => 'user', 'content' => $prompt]],
                tier: $tier,
                systemPrompt: 'You are a senior Moldovan news editor. Polish the editorial briefing for journalistic quality.',
            ));
        } catch (EmergencyHaltException) {
            $this->logger->info('TopicBriefingWriter: polish halted, keeping DRAFT', [
                'briefingId' => $briefing->getId(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->warning('TopicBriefingWriter: polish dispatcher failed, keeping DRAFT', [
                'briefingId' => $briefing->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $parsed = $this->parseJsonResponse($response->content);
        if ($parsed === null) {
            $this->logger->warning('TopicBriefingWriter: polish response not parseable, keeping DRAFT', [
                'briefingId' => $briefing->getId(),
                'rawLength' => \strlen($response->content),
            ]);

            return false;
        }

        $this->applyParsedData($briefing, $parsed);

        return true;
    }

    /**
     * Pre-T57.P4+P5 code path, retained for rollback. Gated by
     * `briefing.llm.use_legacy_gemini_{cadence}` AppSettings. Emits no
     * LlmAgentCallLog rows — intentional regression to pre-migration
     * observability when the rollback is invoked.
     *
     * @param list<PressRelease> $pressReleases
     */
    private function generateViaLegacyPath(
        TopicBriefing $briefing,
        Topic $topic,
        BriefingCadence $cadence,
        array $pressReleases,
    ): ?TopicBriefing {
        $this->logger->info('TopicBriefingWriter: legacy Gemini path active (rollback flag set)', [
            'topicId' => $topic->getId(),
            'cadence' => $cadence->value,
        ]);

        $prompt = $this->buildDraftPrompt($topic, $cadence, $pressReleases);
        $geminiTimeout = $this->settings->getInt('briefing.llm.gemini_timeout', 120);
        $rawDraft = $this->callGeminiLegacy($prompt, $geminiTimeout);

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
            $this->logger->warning('TopicBriefingWriter: failed to parse Gemini JSON (legacy path)', [
                'topicId' => $topic->getId(),
                'rawLength' => \strlen($rawDraft),
            ]);

            return null;
        }

        $this->applyParsedData($briefing, $parsed);
        $briefing->setGeminiDraftRaw($rawDraft);
        $briefing->setStatus(BriefingStatus::DRAFT);
        $briefing->setGeneratedAt(new \DateTimeImmutable());

        $polishEnabled = $this->settings->getBool('briefing.llm.polish_enabled', true);
        if ($polishEnabled) {
            $polished = $this->polishWithClaudeLegacy($briefing);
            if ($polished) {
                $briefing->setClaudePolished(true);
                $briefing->setStatus(BriefingStatus::POLISHED);
            }
        }

        $this->em->flush();

        $this->logger->info('TopicBriefingWriter: generated briefing', [
            'topicId' => $topic->getId(),
            'cadence' => $cadence->value,
            'briefingId' => $briefing->getId(),
            'prCount' => $briefing->getPrCount(),
            'claudePolished' => $briefing->isClaudePolished(),
            'path' => 'legacy_gemini',
        ]);

        return $briefing;
    }

    private function resolveAgentId(BriefingCadence $cadence): string
    {
        return match ($cadence) {
            BriefingCadence::DAILY => self::AGENT_ID_DAILY,
            BriefingCadence::HOURLY => self::AGENT_ID_HOURLY,
            BriefingCadence::WEEKLY => self::AGENT_ID_WEEKLY,
        };
    }

    private function shouldPolish(BriefingCadence $cadence): bool
    {
        return $cadence === BriefingCadence::HOURLY;
    }

    private function shouldFallback(BriefingCadence $cadence): bool
    {
        return $cadence === BriefingCadence::HOURLY;
    }

    private function shouldUseLegacy(BriefingCadence $cadence): bool
    {
        $key = 'briefing.llm.use_legacy_gemini_' . $cadence->value;

        return $this->settings->getBool($key, false);
    }

    /**
     * @param list<PressRelease> $pressReleases
     */
    private function buildDraftPrompt(Topic $topic, BriefingCadence $cadence, array $pressReleases): string
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

    private function callGeminiLegacy(string $prompt, int $timeout): ?string
    {
        try {
            return $this->geminiCli->execute($prompt, ['timeout' => $timeout]);
        } catch (GeminiCliException $e) {
            $this->logger->error('TopicBriefingWriter: Gemini failed (legacy path)', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function polishWithClaudeLegacy(TopicBriefing $briefing): bool
    {
        $prompt = $this->buildClaudePolishPrompt($briefing);

        try {
            $result = $this->claudeCli->chat(
                [['role' => 'user', 'content' => $prompt]],
                LlmModelTier::SONNET->toModelString(),
                'You are a senior Moldovan news editor. Polish the editorial briefing for journalistic quality.',
            );

            $parsed = $this->parseJsonResponse($result);
            if ($parsed === null) {
                $this->logger->warning('TopicBriefingWriter: Claude polish response not parseable (legacy)', [
                    'briefingId' => $briefing->getId(),
                    'rawLength' => \strlen($result),
                ]);

                return false;
            }

            $this->applyParsedData($briefing, $parsed);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('TopicBriefingWriter: Claude polish failed, keeping Gemini draft (legacy)', [
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
