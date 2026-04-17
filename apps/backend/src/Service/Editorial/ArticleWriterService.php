<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Dto\Editorial\ArticleDraft;
use App\Entity\PressRelease;
use App\Entity\StoryCluster;
use App\Entity\Topic;
use App\Entity\TopicBriefing;
use App\Enum\BriefingCadence;
use App\Repository\AppSettingRepository;
use App\Repository\TopicBriefingRepository;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

/**
 * Generates a news article draft from either:
 *   - a Topic + 24h window of press releases (Sprint 52, ADR-019 D1 — primary)
 *   - a StoryCluster (legacy, kept @deprecated for T52.10 quality
 *     comparison; removed wholesale in T52.12)
 *
 * Assembles a context dossier (TopicBriefing summary if available, otherwise
 * structural fallback from PR titles), calls Gemini with a structured
 * journalism prompt, and returns an ArticleDraft DTO on success.
 */
class ArticleWriterService
{
    private const GEMINI_TIMEOUT = 180;
    private const MAX_SOURCES_IN_PROMPT = 8;
    private const EXCERPT_THRESHOLD = 500;
    private const MAX_CONTENT_PER_SOURCE = 3000;
    private const KEY_FACTS_FALLBACK_SOURCES = 3;

    /** @deprecated since Sprint 52 — replaced by AppSetting `article_generation.min_pr_count`. Removed in T52.12. */
    private const MIN_SOURCES_FOR_AI = 3;

    /** @deprecated since Sprint 52 — replaced by AppSetting `article_generation.min_avg_content_length` (same value). Removed in T52.12. */
    private const MIN_AVG_CONTENT_LENGTH = 1500;

    public function __construct(
        private readonly GeminiCliService $geminiCli,
        private readonly LoggerInterface $logger,
        private readonly AppSettingRepository $appSettings,
        private readonly TopicBriefingRepository $topicBriefingRepository,
    ) {}

    // ────────────────────────────────────────────────────────────────────
    //  Sprint 52 — Topic + Window path (ADR-019 D1)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Eligibility gate for the Topic + Window article generation path.
     *
     * Reads thresholds from AppSettings (T52.9):
     *  - article_generation.min_pr_count           (default 1)
     *  - article_generation.min_topic_relevance    (default 2.0)
     *  - article_generation.min_avg_content_length (default 1500)
     *
     * @param PressRelease[] $pressReleases
     * @return array{eligible: bool, reasons: list<string>, prCount: int, avgConfidence: float, avgContentLength: int}
     */
    public function isEligibleForTopicWindow(Topic $topic, array $pressReleases): array
    {
        $minPrCount = $this->appSettings->getInt('article_generation.min_pr_count', 1);
        $minTopicRelevance = $this->appSettings->getFloat('article_generation.min_topic_relevance', 2.0);
        $minAvgContentLength = $this->appSettings->getInt('article_generation.min_avg_content_length', 1500);

        $prCount = \count($pressReleases);
        $reasons = [];

        if ($prCount < $minPrCount) {
            $reasons[] = sprintf('Too few press releases (%d, need %d)', $prCount, $minPrCount);

            return [
                'eligible' => false,
                'reasons' => $reasons,
                'prCount' => $prCount,
                'avgConfidence' => 0.0,
                'avgContentLength' => 0,
            ];
        }

        $totalConfidence = 0.0;
        $totalLength = 0;
        foreach ($pressReleases as $pr) {
            $totalConfidence += $this->getDetectionConfidence($pr, $topic);
            $totalLength += mb_strlen($pr->getContent());
        }
        $avgConfidence = $prCount > 0 ? $totalConfidence / $prCount : 0.0;
        $avgContentLength = $prCount > 0 ? (int) ($totalLength / $prCount) : 0;

        // min_topic_relevance is a SUM-style proxy (count × avg confidence)
        // per ADR-019 D1 importance heuristic. A high-quality singleton
        // (count 1, conf 1.0) → 1.0 relevance; a 3-PR batch with avg 0.7
        // → 2.1 relevance. Default threshold 2.0 keeps ~singletons out
        // unless their detection confidence is very high.
        $relevanceProxy = $prCount * $avgConfidence;
        if ($relevanceProxy < $minTopicRelevance) {
            $reasons[] = sprintf(
                'Topic relevance proxy %.2f below threshold %.2f (count=%d × avgConfidence=%.2f)',
                $relevanceProxy,
                $minTopicRelevance,
                $prCount,
                $avgConfidence,
            );
        }

        if ($avgContentLength < $minAvgContentLength) {
            $reasons[] = sprintf(
                'Content too thin (avg %d chars, need %d)',
                $avgContentLength,
                $minAvgContentLength,
            );
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'prCount' => $prCount,
            'avgConfidence' => $avgConfidence,
            'avgContentLength' => $avgContentLength,
        ];
    }

    /**
     * Generate an article draft from a Topic + Window of press releases.
     *
     * Returns null on Gemini failure or invalid output.
     *
     * @param PressRelease[] $pressReleases Pre-fetched via PressReleaseRepository::findByTopicInWindow (T52.5)
     */
    public function writeArticleFromTopicWindow(
        Topic $topic,
        \DateTimeImmutable $windowStart,
        \DateTimeImmutable $windowEnd,
        array $pressReleases,
    ): ?ArticleDraft {
        if ($pressReleases === []) {
            $this->logger->warning('ArticleWriterService: topic #{id} window {start}–{end} has no PressReleases', [
                'id' => $topic->getId(),
                'start' => $windowStart->format(\DateTimeInterface::ATOM),
                'end' => $windowEnd->format(\DateTimeInterface::ATOM),
            ]);

            return null;
        }

        $briefing = $this->topicBriefingRepository->findLatestForTopic($topic, BriefingCadence::DAILY);

        $prompt = $this->buildPromptForTopicWindow($topic, $pressReleases, $briefing);
        $raw = $this->callGemini($prompt);

        if ($raw === null) {
            return null;
        }

        $parsed = $this->parseResponse($raw);
        if ($parsed === null) {
            $this->logger->warning('ArticleWriterService: failed to parse Gemini JSON for topic #{id}', [
                'id' => $topic->getId(),
                'rawLength' => \strlen($raw),
            ]);

            return null;
        }

        $this->checkDiacritics($parsed, sprintf('topic#%d', $topic->getId() ?? 0));

        $sourceIds = [];
        foreach ($pressReleases as $pr) {
            $id = $pr->getId();
            if ($id !== null) {
                $sourceIds[] = $id;
            }
        }

        $confidence = $this->calculateConfidenceForTopicWindow($topic, $pressReleases, $briefing, $parsed);

        $this->logger->info('ArticleWriterService: generated draft for topic #{id}', [
            'id' => $topic->getId(),
            'titleLength' => mb_strlen($parsed['title']),
            'contentWords' => str_word_count($parsed['content']),
            'sourceCount' => \count($sourceIds),
            'briefingUsed' => $briefing !== null,
            'confidence' => round($confidence, 2),
        ]);

        return new ArticleDraft(
            titleRo: $parsed['title'],
            leadRo: $parsed['lead'],
            contentRo: $parsed['content'],
            metaDescription: $parsed['meta_description'],
            suggestedTags: $parsed['suggested_tags'],
            clusterId: null,
            confidenceScore: $confidence,
            sourcePressReleaseIds: $sourceIds,
            rawPrompt: $prompt,
            rawResponse: $raw,
            topicId: $topic->getId(),
        );
    }

    /**
     * Build the Gemini prompt for the Topic + Window path.
     *
     * @param PressRelease[] $pressReleases
     */
    private function buildPromptForTopicWindow(
        Topic $topic,
        array $pressReleases,
        ?TopicBriefing $briefing,
    ): string {
        $context = $this->assembleContextForTopicWindow($topic, $pressReleases, $briefing);

        return <<<PROMPT
Ești jurnalist la Deschide.md, un portal de știri din Republica Moldova.
Scrie un articol de știri bazat STRICT pe sursele furnizate.

REGULI OBLIGATORII:
- Stil piramidă inversată: informația cea mai importantă PRIMA
- Lead: Maximum 50 de cuvinte, răspunde la Cine/Ce/Când/Unde/De ce
- Atribuire: Citează sursa pentru fiecare afirmație factuală
- Diacritice: EXCLUSIV comma-below: ș (U+0219), ț (U+021B). Cedilla = EROARE.
- Sursele marcate [EXCERPT ONLY] — NU extinde, NU inventă context suplimentar
- Dacă sursele se contrazic pe cifre/date, include AMBELE variante cu [DISCREPANȚĂ]
- NU inventa fapte. Fiecare afirmație TREBUIE să apară în cel puțin o sursă
- Tonul: Modern, factual, accesibil. Evită limbaj propagandistic.
- Conținutul: minimum 300 de cuvinte, structurat cu subtitluri Markdown (##)

RĂSPUNDE EXCLUSIV cu JSON valid (fără markdown fences):
{
  "title": "Titlul articolului (max 100 caractere)",
  "lead": "Lead-ul (max 50 cuvinte)",
  "content": "Conținutul complet în Markdown (min 300 cuvinte)",
  "meta_description": "Meta description SEO (max 160 caractere)",
  "suggested_tags": ["tag1", "tag2", "tag3"]
}

CONTEXT DOSSIER:
---
{$context}
---
PROMPT;
    }

    /**
     * Assemble the context dossier for a Topic + Window generation.
     *
     * Prefers TopicBriefing fields (NotebookLM-derived per ADR-017,
     * Sprint 51a) for summary/whyItMatters/keyFacts when a briefing
     * exists; otherwise falls back to a structural extraction from
     * the first N press release titles for keyFacts and omits the
     * briefing-only sections.
     *
     * @param PressRelease[] $pressReleases
     */
    private function assembleContextForTopicWindow(
        Topic $topic,
        array $pressReleases,
        ?TopicBriefing $briefing,
    ): string {
        $parts = [];

        $parts[] = '## SUBIECT';
        $parts[] = 'Titlu: ' . ($topic->getTitle() ?? 'Subiect fără titlu');
        $parts[] = 'Slug: ' . ($topic->getSlug() ?? 'n/a');
        $parts[] = 'Surse în fereastră: ' . \count($pressReleases);

        if ($briefing !== null) {
            $parts[] = "\n## REZUMAT EDITORIAL (din NotebookLM)";
            if ($briefing->getSummaryShort() !== null) {
                $parts[] = 'Rezumat scurt: ' . $briefing->getSummaryShort();
            }
            if ($briefing->getSummaryLong() !== null) {
                $parts[] = 'Rezumat extins: ' . $briefing->getSummaryLong();
            }
            if ($briefing->getWhyItMatters() !== null) {
                $parts[] = 'De ce contează: ' . $briefing->getWhyItMatters();
            }
            $keyFacts = $briefing->getKeyFacts();
            if ($keyFacts !== null && $keyFacts !== []) {
                $parts[] = "Fapte cheie:\n- " . implode("\n- ", $keyFacts);
            }
        } else {
            // Fallback per ADR-019 D1 audit gap: structural keyFacts from
            // the first N PR titles when no NotebookLM briefing exists.
            $fallbackFacts = [];
            foreach (\array_slice($pressReleases, 0, self::KEY_FACTS_FALLBACK_SOURCES) as $idx => $pr) {
                $fallbackFacts[] = sprintf('Sursa %d: %s', $idx + 1, $pr->getTitle());
            }
            if ($fallbackFacts !== []) {
                $parts[] = "\n## FAPTE CHEIE (extras structural din titluri)";
                $parts[] = '- ' . implode("\n- ", $fallbackFacts);
            }
        }

        $parts[] = $this->renderSourcesSection($pressReleases);

        return implode("\n", $parts);
    }

    /**
     * Compute confidence score using topic-window proxies.
     *
     * Mirrors the cluster-based calculation but substitutes:
     *  - factor 4 (cluster summary fields) → TopicBriefing fields
     *  - factor 5 (cluster importance score) → count(PRs) × avg(detection
     *    confidence) per ADR-019 D1 importance heuristic
     *
     * @param PressRelease[]                                                         $pressReleases
     * @param array{title: string, lead: string, content: string, meta_description: string, suggested_tags?: list<string>} $parsed
     */
    private function calculateConfidenceForTopicWindow(
        Topic $topic,
        array $pressReleases,
        ?TopicBriefing $briefing,
        array $parsed,
    ): float {
        $score = 0.0;
        $sourceCount = \count($pressReleases);

        // Factor 1: Number of sources (max 0.3) — same as cluster path
        $score += min(0.3, $sourceCount * 0.06);

        // Factor 2: Full-text source ratio (max 0.25) — same as cluster path
        $fullTextCount = 0;
        foreach ($pressReleases as $pr) {
            if (mb_strlen($pr->getContent()) >= self::EXCERPT_THRESHOLD) {
                $fullTextCount++;
            }
        }
        $fullTextRatio = $sourceCount > 0 ? $fullTextCount / $sourceCount : 0.0;
        $score += $fullTextRatio * 0.25;

        // Factor 3: Content length of output (max 0.2) — same as cluster path
        $contentWords = str_word_count($parsed['content']);
        $score += min(0.2, ($contentWords / 500) * 0.2);

        // Factor 4: TopicBriefing data presence (max 0.15) — replaces
        // cluster summary check
        if ($briefing !== null) {
            if ($briefing->getSummaryShort() !== null) {
                $score += 0.05;
            }
            if ($briefing->getSummaryLong() !== null) {
                $score += 0.05;
            }
            if ($briefing->getKeyFacts() !== null && $briefing->getKeyFacts() !== []) {
                $score += 0.05;
            }
        }

        // Factor 5: Topic relevance proxy (max 0.1) — replaces cluster
        // importance score. count × avg(detection confidence) per ADR-019
        // D1 heuristic, normalized into the 0.0–0.1 contribution range.
        $totalConfidence = 0.0;
        foreach ($pressReleases as $pr) {
            $totalConfidence += $this->getDetectionConfidence($pr, $topic);
        }
        $avgConfidence = $sourceCount > 0 ? $totalConfidence / $sourceCount : 0.0;
        $relevanceProxy = $sourceCount * $avgConfidence;
        // Normalize: a relevance ≥ 5.0 saturates the factor. 5.0 maps to
        // a 5-PR batch with perfect detection confidence.
        $score += min(0.1, $relevanceProxy / 50.0);

        return min(1.0, $score);
    }

    /**
     * Look up the PressReleaseTopic detection confidence for the given
     * Topic on the given PressRelease. Returns 0.5 if no link found
     * (defensive default — should not happen for PRs sourced via
     * findByTopicInWindow which inner-joins on the topic link).
     */
    private function getDetectionConfidence(PressRelease $pr, Topic $topic): float
    {
        foreach ($pr->getPressReleaseTopics() as $link) {
            if ($link->getTopic()->getId() === $topic->getId()) {
                return $link->getConfidence();
            }
        }

        return 0.5;
    }

    // ────────────────────────────────────────────────────────────────────
    //  Sprint 52 — Legacy StoryCluster path (kept FUNCTIONAL for T52.10
    //  quality comparison; removed wholesale in T52.12 per ADR-019 D6)
    // ────────────────────────────────────────────────────────────────────

    /**
     * @deprecated since Sprint 52 (T52.4) — superseded by
     *             {@see isEligibleForTopicWindow()}. Kept functional
     *             for the T52.10 quality comparison checkpoint;
     *             removed wholesale in T52.12 (ADR-019 D6).
     *
     * @return array{eligible: bool, reason: ?string, sourceCount: int, avgLength: int}
     */
    public function isEligibleForAiGeneration(StoryCluster $cluster): array
    {
        $pressReleases = $cluster->getPressReleases();
        $count = $pressReleases->count();

        if ($count === 0) {
            return ['eligible' => false, 'reason' => 'No press releases', 'sourceCount' => 0, 'avgLength' => 0];
        }

        if ($count < self::MIN_SOURCES_FOR_AI) {
            return [
                'eligible' => false,
                'reason' => sprintf('Too few sources (%d, need %d)', $count, self::MIN_SOURCES_FOR_AI),
                'sourceCount' => $count,
                'avgLength' => 0,
            ];
        }

        $totalLength = 0;
        foreach ($pressReleases as $pr) {
            $totalLength += mb_strlen($pr->getContent());
        }
        $avgLength = (int) ($totalLength / $count);

        if ($avgLength < self::MIN_AVG_CONTENT_LENGTH) {
            return [
                'eligible' => false,
                'reason' => sprintf('Content too thin (avg %d chars, need %d)', $avgLength, self::MIN_AVG_CONTENT_LENGTH),
                'sourceCount' => $count,
                'avgLength' => $avgLength,
            ];
        }

        return ['eligible' => true, 'reason' => null, 'sourceCount' => $count, 'avgLength' => $avgLength];
    }

    /**
     * @deprecated since Sprint 52 (T52.4) — superseded by
     *             {@see writeArticleFromTopicWindow()}. Kept FUNCTIONAL
     *             (not a delegate) for the T52.10 quality comparison
     *             checkpoint; removed wholesale in T52.12 (ADR-019 D6).
     */
    public function generateArticle(StoryCluster $cluster): ?ArticleDraft
    {
        $pressReleases = $cluster->getPressReleases();
        if ($pressReleases->isEmpty()) {
            $this->logger->warning('ArticleWriterService: cluster #{id} has no PressReleases', [
                'id' => $cluster->getId(),
            ]);

            return null;
        }

        $prompt = $this->buildPrompt($cluster);
        $raw = $this->callGemini($prompt);

        if ($raw === null) {
            return null;
        }

        $parsed = $this->parseResponse($raw);
        if ($parsed === null) {
            $this->logger->warning('ArticleWriterService: failed to parse Gemini JSON for cluster #{id}', [
                'id' => $cluster->getId(),
                'rawLength' => \strlen($raw),
            ]);

            return null;
        }

        $this->checkDiacritics($parsed, sprintf('cluster#%d', $cluster->getId() ?? 0));

        $sourceIds = [];
        foreach ($pressReleases as $pr) {
            $sourceIds[] = $pr->getId();
        }

        $confidence = $this->calculateConfidence($cluster, $parsed);

        $this->logger->info('ArticleWriterService: generated draft for cluster #{id}', [
            'id' => $cluster->getId(),
            'titleLength' => mb_strlen($parsed['title']),
            'contentWords' => str_word_count($parsed['content']),
            'sourceCount' => \count($sourceIds),
            'confidence' => round($confidence, 2),
        ]);

        return new ArticleDraft(
            titleRo: $parsed['title'],
            leadRo: $parsed['lead'],
            contentRo: $parsed['content'],
            metaDescription: $parsed['meta_description'],
            suggestedTags: $parsed['suggested_tags'],
            clusterId: $cluster->getId(),
            confidenceScore: $confidence,
            sourcePressReleaseIds: $sourceIds,
            rawPrompt: $prompt,
            rawResponse: $raw,
        );
    }

    /** @deprecated since Sprint 52 — see {@see generateArticle()}. */
    private function buildPrompt(StoryCluster $cluster): string
    {
        $context = $this->assembleContext($cluster);

        return <<<PROMPT
Ești jurnalist la Deschide.md, un portal de știri din Republica Moldova.
Scrie un articol de știri bazat STRICT pe sursele furnizate.

REGULI OBLIGATORII:
- Stil piramidă inversată: informația cea mai importantă PRIMA
- Lead: Maximum 50 de cuvinte, răspunde la Cine/Ce/Când/Unde/De ce
- Atribuire: Citează sursa pentru fiecare afirmație factuală
- Diacritice: EXCLUSIV comma-below: ș (U+0219), ț (U+021B). Cedilla = EROARE.
- Sursele marcate [EXCERPT ONLY] — NU extinde, NU inventă context suplimentar
- Dacă sursele se contrazic pe cifre/date, include AMBELE variante cu [DISCREPANȚĂ]
- NU inventa fapte. Fiecare afirmație TREBUIE să apară în cel puțin o sursă
- Tonul: Modern, factual, accesibil. Evită limbaj propagandistic.
- Conținutul: minimum 300 de cuvinte, structurat cu subtitluri Markdown (##)

RĂSPUNDE EXCLUSIV cu JSON valid (fără markdown fences):
{
  "title": "Titlul articolului (max 100 caractere)",
  "lead": "Lead-ul (max 50 cuvinte)",
  "content": "Conținutul complet în Markdown (min 300 cuvinte)",
  "meta_description": "Meta description SEO (max 160 caractere)",
  "suggested_tags": ["tag1", "tag2", "tag3"]
}

CONTEXT DOSSIER:
---
{$context}
---
PROMPT;
    }

    /** @deprecated since Sprint 52 — see {@see generateArticle()}. */
    private function assembleContext(StoryCluster $cluster): string
    {
        $parts = [];

        $parts[] = '## REZUMAT CLUSTER';
        $parts[] = 'Titlu: ' . $cluster->getPrimaryHeadline();
        $parts[] = 'Scor importanță: ' . round($cluster->getImportanceScore(), 2);

        if ($cluster->getSummaryShort() !== null) {
            $parts[] = 'Rezumat scurt: ' . $cluster->getSummaryShort();
        }
        if ($cluster->getSummaryMedium() !== null) {
            $parts[] = 'Rezumat mediu: ' . $cluster->getSummaryMedium();
        }
        if ($cluster->getWhyItMatters() !== null) {
            $parts[] = 'De ce contează: ' . $cluster->getWhyItMatters();
        }
        $keyFacts = $cluster->getKeyFacts();
        if ($keyFacts !== null && \count($keyFacts) > 0) {
            $parts[] = "Fapte cheie:\n- " . implode("\n- ", $keyFacts);
        }

        $parts[] = $this->renderSourcesSection(iterator_to_array($cluster->getPressReleases()));

        return implode("\n", $parts);
    }

    /**
     * @deprecated since Sprint 52 — see {@see generateArticle()}.
     *
     * @param array{title: string, lead: string, content: string, meta_description: string, suggested_tags: list<string>} $parsed
     */
    private function calculateConfidence(StoryCluster $cluster, array $parsed): float
    {
        $score = 0.0;

        $sourceCount = $cluster->getPressReleases()->count();
        $score += min(0.3, $sourceCount * 0.06);

        $fullTextCount = 0;
        foreach ($cluster->getPressReleases() as $pr) {
            if (mb_strlen($pr->getContent()) >= self::EXCERPT_THRESHOLD) {
                $fullTextCount++;
            }
        }
        $fullTextRatio = $sourceCount > 0 ? $fullTextCount / $sourceCount : 0;
        $score += $fullTextRatio * 0.25;

        $contentWords = str_word_count($parsed['content']);
        $score += min(0.2, ($contentWords / 500) * 0.2);

        if ($cluster->getSummaryShort() !== null) {
            $score += 0.05;
        }
        if ($cluster->getSummaryMedium() !== null) {
            $score += 0.05;
        }
        if ($cluster->getKeyFacts() !== null) {
            $score += 0.05;
        }

        $score += min(0.1, $cluster->getImportanceScore() * 0.1);

        return min(1.0, $score);
    }

    // ────────────────────────────────────────────────────────────────────
    //  Shared helpers (used by both paths)
    // ────────────────────────────────────────────────────────────────────

    /**
     * Render the SURSE section for either path.
     *
     * @param PressRelease[] $pressReleases
     */
    private function renderSourcesSection(array $pressReleases): string
    {
        $fullSources = [];
        $excerptSources = [];

        foreach ($pressReleases as $pr) {
            $contentLength = mb_strlen($pr->getContent());

            $entry = [
                'title' => $pr->getTitle(),
                'source' => $pr->getSource()?->getName() ?? $pr->getSourceName() ?? 'Unknown',
                'credibility' => $pr->getSource()?->getCredibilityWeight() ?? 0.5,
                'content' => $pr->getContent(),
                'publishedAt' => $pr->getReceivedAt()->format('Y-m-d H:i'),
                'language' => $pr->getDetectedLanguage() ?? 'ro',
                'isExcerpt' => $contentLength < self::EXCERPT_THRESHOLD,
            ];

            if ($entry['isExcerpt']) {
                $excerptSources[] = $entry;
            } else {
                $fullSources[] = $entry;
            }
        }

        usort($fullSources, fn (array $a, array $b) => $b['credibility'] <=> $a['credibility']);
        usort($excerptSources, fn (array $a, array $b) => $b['credibility'] <=> $a['credibility']);

        $allSources = array_merge($fullSources, $excerptSources);
        $allSources = \array_slice($allSources, 0, self::MAX_SOURCES_IN_PROMPT);

        $lines = ["\n## SURSE COMPLETE (" . \count($fullSources) . ' cu text integral)'];
        $idx = 1;
        foreach ($allSources as $source) {
            $tag = $source['isExcerpt'] ? ' [EXCERPT ONLY]' : '';
            $truncatedContent = mb_substr(
                strip_tags($source['content']),
                0,
                self::MAX_CONTENT_PER_SOURCE,
            );

            $lines[] = sprintf(
                "\n--- Sursa %d%s ---\nTitlu: %s\nPublicație: %s (credibilitate: %.1f)\nLimbă: %s\nData: %s\nConținut:\n%s",
                $idx,
                $tag,
                $source['title'],
                $source['source'],
                $source['credibility'],
                $source['language'],
                $source['publishedAt'],
                $truncatedContent,
            );
            $idx++;
        }

        return implode("\n", $lines);
    }

    private function callGemini(string $prompt): ?string
    {
        try {
            return $this->geminiCli->execute($prompt, ['timeout' => self::GEMINI_TIMEOUT]);
        } catch (GeminiCliException $e) {
            $this->logger->error('ArticleWriterService: Gemini exception', [
                'error' => $e->getMessage(),
                'isTimeout' => $e->isTimeout(),
            ]);

            return null;
        }
    }

    /**
     * @return array{title: string, lead: string, content: string, meta_description: string, suggested_tags: list<string>}|null
     */
    private function parseResponse(string $raw): ?array
    {
        $json = $raw;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $json, $m)) {
            $json = $m[1];
        }

        $start = strpos($json, '{');
        $end = strrpos($json, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $json = substr($json, $start, $end - $start + 1);
        }

        $json = trim($json);
        $data = json_decode($json, true);

        if (!\is_array($data)) {
            return null;
        }

        $required = ['title', 'lead', 'content', 'meta_description'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || !\is_string($data[$field]) || $data[$field] === '') {
                $this->logger->warning('ArticleWriterService: missing required field "{field}"', [
                    'field' => $field,
                ]);

                return null;
            }
        }

        if (!isset($data['suggested_tags']) || !\is_array($data['suggested_tags'])) {
            $data['suggested_tags'] = [];
        }

        return $data;
    }

    /**
     * Warn if cedilla diacritics detected in output.
     *
     * @param array{title: string, lead: string, content: string, meta_description: string, suggested_tags: list<string>} $parsed
     * @param string                                                                                                       $sourceLabel Identifier for log context (e.g. "topic#42" or "cluster#7")
     */
    private function checkDiacritics(array $parsed, string $sourceLabel): void
    {
        $text = $parsed['title'] . $parsed['lead'] . $parsed['content'];

        // Cedilla: ş (U+015F) and ţ (U+0163)
        if (preg_match('/[\x{015F}\x{0163}]/u', $text)) {
            $this->logger->warning('ArticleWriterService: CEDILLA diacritics detected in output for {source}. Expected comma-below (ș/ț).', [
                'source' => $sourceLabel,
            ]);
        }
    }
}
