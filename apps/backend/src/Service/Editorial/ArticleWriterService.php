<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Dto\Editorial\ArticleDraft;
use App\Entity\StoryCluster;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

/**
 * Generates a news article draft from a StoryCluster using Gemini CLI.
 *
 * Assembles a context dossier from cluster summary + press release texts,
 * calls Gemini with a structured journalism prompt, and returns an
 * ArticleDraft DTO on success.
 */
class ArticleWriterService
{
    private const GEMINI_TIMEOUT = 180;
    private const MAX_SOURCES_IN_PROMPT = 8;
    private const EXCERPT_THRESHOLD = 500;
    private const MAX_CONTENT_PER_SOURCE = 3000;

    public function __construct(
        private readonly GeminiCliService $geminiCli,
        private readonly LoggerInterface $logger,
    ) {}

    private const MIN_SOURCES_FOR_AI = 3;
    private const MIN_AVG_CONTENT_LENGTH = 1500;

    /**
     * Check if a cluster has enough content depth for AI article generation.
     *
     * Requires: minimum 3 distinct sources AND average content length >= 1500 chars.
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
            $totalLength += mb_strlen($pr->getContent() ?? '');
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
     * Generate an article draft from a cluster's press releases.
     *
     * Returns null on Gemini failure or invalid output.
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

        // Validate diacritics — warn on cedilla but don't reject
        $this->checkDiacritics($parsed, $cluster->getId());

        // Collect source PR IDs
        $sourceIds = [];
        foreach ($pressReleases as $pr) {
            $sourceIds[] = $pr->getId();
        }

        // Calculate confidence score
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
            suggestedTags: $parsed['suggested_tags'] ?? [],
            clusterId: $cluster->getId(),
            confidenceScore: $confidence,
            sourcePressReleaseIds: $sourceIds,
            rawPrompt: $prompt,
            rawResponse: $raw,
        );
    }

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

    private function assembleContext(StoryCluster $cluster): string
    {
        $parts = [];

        // Section 1: Cluster summary
        $parts[] = "## REZUMAT CLUSTER";
        $parts[] = "Titlu: " . $cluster->getPrimaryHeadline();
        $parts[] = "Scor importanță: " . round($cluster->getImportanceScore(), 2);

        if ($cluster->getSummaryShort() !== null) {
            $parts[] = "Rezumat scurt: " . $cluster->getSummaryShort();
        }
        if ($cluster->getSummaryMedium() !== null) {
            $parts[] = "Rezumat mediu: " . $cluster->getSummaryMedium();
        }
        if ($cluster->getWhyItMatters() !== null) {
            $parts[] = "De ce contează: " . $cluster->getWhyItMatters();
        }
        $keyFacts = $cluster->getKeyFacts();
        if ($keyFacts !== null && \count($keyFacts) > 0) {
            $parts[] = "Fapte cheie:\n- " . implode("\n- ", $keyFacts);
        }

        // Section 2+3: Source press releases — full-text first, excerpts last
        $fullSources = [];
        $excerptSources = [];

        foreach ($cluster->getPressReleases() as $pr) {
            $contentLength = mb_strlen($pr->getContent() ?? '');

            $entry = [
                'title' => $pr->getTitle(),
                'source' => $pr->getSource()?->getName() ?? $pr->getSourceName() ?? 'Unknown',
                'credibility' => $pr->getSource()?->getCredibilityWeight() ?? 0.5,
                'content' => $pr->getContent() ?? '',
                'publishedAt' => $pr->getReceivedAt()?->format('Y-m-d H:i') ?? 'n/a',
                'language' => $pr->getDetectedLanguage() ?? 'ro',
                'isExcerpt' => $contentLength < self::EXCERPT_THRESHOLD,
            ];

            if ($entry['isExcerpt']) {
                $excerptSources[] = $entry;
            } else {
                $fullSources[] = $entry;
            }
        }

        // Sort full sources by credibility descending
        usort($fullSources, fn(array $a, array $b) => $b['credibility'] <=> $a['credibility']);
        usort($excerptSources, fn(array $a, array $b) => $b['credibility'] <=> $a['credibility']);

        $allSources = array_merge($fullSources, $excerptSources);
        $allSources = \array_slice($allSources, 0, self::MAX_SOURCES_IN_PROMPT);

        $parts[] = "\n## SURSE COMPLETE (" . \count($fullSources) . " cu text integral)";
        $idx = 1;
        foreach ($allSources as $source) {
            $tag = $source['isExcerpt'] ? ' [EXCERPT ONLY]' : '';
            $truncatedContent = mb_substr(
                strip_tags($source['content']),
                0,
                self::MAX_CONTENT_PER_SOURCE
            );

            $parts[] = sprintf(
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

        return implode("\n", $parts);
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
        // Strip markdown code block wrappers
        $json = $raw;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $json, $m)) {
            $json = $m[1];
        }

        // Try extracting JSON object
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

        // Validate required fields
        $required = ['title', 'lead', 'content', 'meta_description'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || !\is_string($data[$field]) || $data[$field] === '') {
                $this->logger->warning('ArticleWriterService: missing required field "{field}"', [
                    'field' => $field,
                ]);
                return null;
            }
        }

        // Ensure suggested_tags is an array
        if (!isset($data['suggested_tags']) || !\is_array($data['suggested_tags'])) {
            $data['suggested_tags'] = [];
        }

        return $data;
    }

    /**
     * Calculate a confidence score based on source quality and output richness.
     */
    private function calculateConfidence(StoryCluster $cluster, array $parsed): float
    {
        $score = 0.0;

        // Factor 1: Number of sources (max 0.3)
        $sourceCount = $cluster->getPressReleases()->count();
        $score += min(0.3, $sourceCount * 0.06);

        // Factor 2: Full-text source ratio (max 0.25)
        $fullTextCount = 0;
        foreach ($cluster->getPressReleases() as $pr) {
            if (mb_strlen($pr->getContent() ?? '') >= self::EXCERPT_THRESHOLD) {
                $fullTextCount++;
            }
        }
        $fullTextRatio = $sourceCount > 0 ? $fullTextCount / $sourceCount : 0;
        $score += $fullTextRatio * 0.25;

        // Factor 3: Content length of output (max 0.2)
        $contentWords = str_word_count($parsed['content']);
        $score += min(0.2, ($contentWords / 500) * 0.2);

        // Factor 4: Has cluster summary data (max 0.15)
        if ($cluster->getSummaryShort() !== null) {
            $score += 0.05;
        }
        if ($cluster->getSummaryMedium() !== null) {
            $score += 0.05;
        }
        if ($cluster->getKeyFacts() !== null) {
            $score += 0.05;
        }

        // Factor 5: Importance score (max 0.1)
        $score += min(0.1, $cluster->getImportanceScore() * 0.1);

        return min(1.0, $score);
    }

    /**
     * Warn if cedilla diacritics detected in output.
     */
    private function checkDiacritics(array $parsed, int $clusterId): void
    {
        $text = $parsed['title'] . $parsed['lead'] . $parsed['content'];

        // Check for cedilla: ş (U+015F) and ţ (U+0163)
        if (preg_match('/[\x{015F}\x{0163}]/u', $text)) {
            $this->logger->warning('ArticleWriterService: CEDILLA diacritics detected in output for cluster #{id}. Expected comma-below (ș/ț).', [
                'id' => $clusterId,
            ]);
        }
    }
}
