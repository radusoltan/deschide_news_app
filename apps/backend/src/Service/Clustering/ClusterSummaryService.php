<?php

declare(strict_types=1);

namespace App\Service\Clustering;

use App\Entity\StoryCluster;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Generates AI summaries for StoryClusters using Gemini CLI.
 *
 * For each cluster, produces:
 * - summaryShort: 1-2 sentence TL;DR
 * - summaryMedium: 3-5 sentence overview
 * - whyItMatters: Editorial "why this matters" context
 * - keyFacts: Array of 3-5 bullet-point facts
 *
 * Generates in Romanian (primary), with English embedded in JSON.
 */
class ClusterSummaryService
{
    private const GEMINI_TIMEOUT = 120;
    private const MAX_CONTENT_PER_PR = 500;
    private const MAX_PRS_IN_PROMPT = 10;

    public function __construct(
        private readonly string $geminiCliPath,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Generate AI summary for a single cluster.
     * Returns true if summary was generated, false on failure.
     */
    public function summarize(StoryCluster $cluster): bool
    {
        $pressReleases = $cluster->getPressReleases();
        if ($pressReleases->isEmpty()) {
            $this->logger->warning('ClusterSummaryService: cluster #{id} has no PressReleases', [
                'id' => $cluster->getId(),
            ]);
            return false;
        }

        $prompt = $this->buildPrompt($cluster);
        $raw = $this->callGemini($prompt);

        if ($raw === null) {
            $this->logger->warning('ClusterSummaryService: Gemini failed for cluster #{id}', [
                'id' => $cluster->getId(),
            ]);
            return false;
        }

        $parsed = $this->parseResponse($raw);
        if ($parsed === null) {
            $this->logger->warning('ClusterSummaryService: failed to parse JSON for cluster #{id}', [
                'id' => $cluster->getId(),
                'rawLength' => \strlen($raw),
            ]);
            return false;
        }

        // Apply to entity
        $cluster->setSummaryShort($parsed['summary_short'] ?? null);
        $cluster->setSummaryMedium($parsed['summary_medium'] ?? null);
        $cluster->setWhyItMatters($parsed['why_it_matters'] ?? null);
        $cluster->setKeyFacts($parsed['key_facts'] ?? null);

        $this->em->flush();

        $this->logger->info('ClusterSummaryService: generated summary for cluster #{id}', [
            'id' => $cluster->getId(),
            'headline' => mb_substr($cluster->getPrimaryHeadline(), 0, 60),
        ]);

        return true;
    }

    private function buildPrompt(StoryCluster $cluster): string
    {
        $headline = $cluster->getPrimaryHeadline();
        $sourceCount = $cluster->getSourceCount();
        $articleCount = $cluster->getArticleCount();

        $articles = '';
        $count = 0;
        foreach ($cluster->getPressReleases() as $pr) {
            if ($count >= self::MAX_PRS_IN_PROMPT) {
                break;
            }

            $title = $pr->getTitle();
            $source = $pr->getSource()?->getName() ?? $pr->getSourceName() ?? 'Unknown';
            $content = mb_substr(strip_tags($pr->getContent()), 0, self::MAX_CONTENT_PER_PR);
            $lang = $pr->getDetectedLanguage() ?? 'unknown';

            $articles .= <<<ARTICLE

            --- Article {$count} [{$lang}] ({$source}) ---
            Title: {$title}
            Content: {$content}
            ARTICLE;

            $count++;
        }

        return <<<PROMPT
        You are a senior news editor at Deschide.md, a Moldovan news portal.

        Analyze the following cluster of {$articleCount} news articles from {$sourceCount} sources about the same event/topic.

        Cluster headline: {$headline}

        Articles:
        {$articles}

        Generate a JSON summary with these fields:

        {
          "summary_short": "1-2 sentences, TL;DR in Romanian. Use comma-below diacritics (ș, ț).",
          "summary_medium": "3-5 sentences, overview in Romanian with context. Use comma-below diacritics.",
          "why_it_matters": "2-3 sentences explaining relevance for Moldova/region. Romanian, comma-below.",
          "key_facts": ["fact 1 in Romanian", "fact 2", "fact 3", "fact 4", "fact 5"]
        }

        Rules:
        - Write in Romanian with comma-below diacritics exclusively (ș, ț NOT ş, ţ)
        - Base ALL facts strictly on the provided articles. DO NOT invent information.
        - key_facts: 3-5 bullet points, each 1 sentence
        - summary_short: max 50 words
        - summary_medium: max 150 words
        - why_it_matters: focus on Republic of Moldova, EU integration, regional impact
        - Return ONLY valid JSON, no markdown code blocks
        PROMPT;
    }

    private function callGemini(string $prompt): ?string
    {
        $process = new Process([$this->geminiCliPath, '-p', $prompt]);
        $process->setTimeout(self::GEMINI_TIMEOUT);

        $start = microtime(true);

        try {
            $process->run();

            $duration = round((microtime(true) - $start) * 1000);

            if (!$process->isSuccessful()) {
                $this->logger->error('ClusterSummaryService: Gemini CLI failed', [
                    'exitCode' => $process->getExitCode(),
                    'stderr' => mb_substr($process->getErrorOutput(), 0, 300),
                    'duration_ms' => $duration,
                ]);
                return null;
            }

            $output = trim($process->getOutput());

            $this->logger->debug('ClusterSummaryService: Gemini call', [
                'duration_ms' => $duration,
                'outputLength' => \strlen($output),
            ]);

            return $output;
        } catch (\Throwable $e) {
            $this->logger->error('ClusterSummaryService: Gemini exception', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse JSON response from Gemini, stripping markdown code blocks if present.
     *
     * @return array{summary_short: string, summary_medium: string, why_it_matters: string, key_facts: list<string>}|null
     */
    private function parseResponse(string $raw): ?array
    {
        // Strip markdown code block wrappers
        $json = $raw;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $json, $m)) {
            $json = $m[1];
        }

        $json = trim($json);

        $data = json_decode($json, true);
        if (!\is_array($data)) {
            return null;
        }

        // Validate required fields
        $required = ['summary_short', 'summary_medium', 'why_it_matters', 'key_facts'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || ($field !== 'key_facts' && !\is_string($data[$field]))) {
                return null;
            }
        }

        if (!\is_array($data['key_facts'])) {
            return null;
        }

        return $data;
    }
}
