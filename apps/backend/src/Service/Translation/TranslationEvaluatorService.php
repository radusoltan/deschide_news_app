<?php

declare(strict_types=1);

namespace App\Service\Translation;

use App\Dto\Translation\TranslationEvaluationResult;
use App\Dto\Translation\TranslationOptimizationResult;
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final class TranslationEvaluatorService
{
    private const TIMEOUT = 90;
    private const SCORE_THRESHOLD = 0.85;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GeminiStructuredTranslator $translator,
        private readonly LoggerInterface $logger,
        private readonly string $geminiCliPath,
    ) {}

    /**
     * Evaluate translation quality using Gemini as judge.
     */
    public function evaluate(
        string $originalText,
        string $translatedText,
        string $sourceLang,
        string $targetLang,
    ): TranslationEvaluationResult {
        $langNames = ['ro' => 'română', 'en' => 'engleză', 'ru' => 'rusă'];
        $sourceLabel = $langNames[$sourceLang] ?? $sourceLang;
        $targetLabel = $langNames[$targetLang] ?? $targetLang;

        // Truncate to avoid process limits
        $originalTruncated = mb_substr(strip_tags($originalText), 0, 15000);
        $translatedTruncated = mb_substr(strip_tags($translatedText), 0, 15000);

        $prompt = <<<PROMPT
Ești un evaluator de calitate lingvistică pentru o redacție de știri din Republica Moldova.
Evaluează traducerea din {$sourceLabel} în {$targetLabel}.

Text original:
{$originalTruncated}

Traducere:
{$translatedTruncated}

Returnează DOAR JSON valid:
{
  "score": 0.85,
  "issues": [
    {"type": "accuracy", "severity": "minor", "description": "..."}
  ],
  "needs_revision": true,
  "revision_instructions": "Corectează: ..."
}

Criterii:
- accuracy: fidelitatea față de original (fapte, cifre, nume proprii)
- fluency: naturalețea în limba țintă
- diacritics: ș/ț comma-below OBLIGATORIU (NU ş/ţ cedilla) pentru română
- tone: ton jurnalistic profesional, neutru
- completeness: nimic omis din original

Prag: score >= 0.85 și zero issues critical/major → needs_revision: false
PROMPT;

        $raw = $this->callGemini($prompt);

        if ($raw === null) {
            $this->logger->warning('TranslationEvaluator: Gemini returned null, defaulting to needs_review');

            return new TranslationEvaluationResult(
                score: 0.0,
                issues: [['type' => 'system', 'severity' => 'critical', 'description' => 'Evaluation failed — Gemini unavailable']],
                needsRevision: true,
                revisionInstructions: null,
            );
        }

        return $this->parseEvaluationResponse($raw);
    }

    /**
     * Full evaluate-optimize loop for an article translation.
     */
    public function evaluateAndOptimize(
        int $articleId,
        string $targetLang,
        int $maxIterations = 2,
        float $threshold = self::SCORE_THRESHOLD,
    ): TranslationOptimizationResult {
        $article = $this->em->find(Article::class, $articleId);

        if ($article === null) {
            throw new \InvalidArgumentException("Article #{$articleId} not found");
        }

        // Get original text (Romanian = default locale)
        $originalText = $article->getContent() ?? '';
        $originalTitle = $article->getTitle() ?? '';

        if ($originalText === '') {
            return new TranslationOptimizationResult($articleId, $targetLang, 0.0, 0.0, 0, 'needs_review');
        }

        // Get current translation from ext_translations
        $translatedContent = $this->getTranslation($article, 'content', $targetLang);
        $translatedTitle = $this->getTranslation($article, 'title', $targetLang);

        if ($translatedContent === null || $translatedContent === '') {
            $this->logger->info('TranslationEvaluator: no translation found', [
                'articleId' => $articleId,
                'lang' => $targetLang,
            ]);

            return new TranslationOptimizationResult($articleId, $targetLang, 0.0, 0.0, 0, 'needs_review');
        }

        // First evaluation
        $evaluation = $this->evaluate($originalText, $translatedContent, 'ro', $targetLang);
        $initialScore = $evaluation->score;
        $currentScore = $initialScore;
        $iterations = 0;

        $this->logger->info('TranslationEvaluator: initial evaluation', [
            'articleId' => $articleId,
            'lang' => $targetLang,
            'score' => $initialScore,
            'needsRevision' => $evaluation->needsRevision,
            'issues' => \count($evaluation->issues),
        ]);

        // Optimize loop
        while ($evaluation->needsRevision && $iterations < $maxIterations) {
            $iterations++;

            $this->logger->info('TranslationEvaluator: optimization iteration', [
                'articleId' => $articleId,
                'lang' => $targetLang,
                'iteration' => $iterations,
            ]);

            // Re-translate with revision instructions as context
            $revisedTranslation = $this->translator->translate(
                $originalTitle . "\n\n" . $originalText,
                $evaluation->revisionInstructions ?? 'Îmbunătățește calitatea traducerii.',
                'ro',
                $targetLang,
            );

            if ($revisedTranslation === null) {
                $this->logger->warning('TranslationEvaluator: re-translation failed', [
                    'articleId' => $articleId,
                    'iteration' => $iterations,
                ]);
                break;
            }

            // Save revised translation
            $this->saveTranslation($article, $targetLang, $revisedTranslation);
            $translatedContent = $revisedTranslation['translated_body'] ?? $translatedContent;

            // Re-evaluate
            $evaluation = $this->evaluate($originalText, $translatedContent, 'ro', $targetLang);
            $currentScore = $evaluation->score;

            $this->logger->info('TranslationEvaluator: re-evaluation after iteration', [
                'articleId' => $articleId,
                'lang' => $targetLang,
                'iteration' => $iterations,
                'score' => $currentScore,
            ]);
        }

        // Determine final status
        $finalStatus = $currentScore >= $threshold ? 'complete' : 'needs_review';

        // Update article translation status
        $article->setTranslationStatus($finalStatus);
        $this->em->flush();

        $this->logger->info('TranslationEvaluator: optimization complete', [
            'articleId' => $articleId,
            'lang' => $targetLang,
            'initialScore' => $initialScore,
            'finalScore' => $currentScore,
            'iterations' => $iterations,
            'status' => $finalStatus,
        ]);

        return new TranslationOptimizationResult(
            $articleId,
            $targetLang,
            $initialScore,
            $currentScore,
            $iterations,
            $finalStatus,
        );
    }

    private function getTranslation(Article $article, string $field, string $locale): ?string
    {
        /** @var TranslationRepository $repo */
        $repo = $this->em->getRepository('Gedmo\Translatable\Entity\Translation');

        $translations = $repo->findTranslations($article);

        return $translations[$locale][$field] ?? null;
    }

    /**
     * @param array<string, mixed> $translationData
     */
    private function saveTranslation(Article $article, string $locale, array $translationData): void
    {
        /** @var TranslationRepository $repo */
        $repo = $this->em->getRepository('Gedmo\Translatable\Entity\Translation');

        if (!empty($translationData['translated_headline'])) {
            $repo->translate($article, 'title', $locale, $translationData['translated_headline']);
        }

        if (!empty($translationData['translated_body'])) {
            $repo->translate($article, 'content', $locale, $translationData['translated_body']);
        }

        if (!empty($translationData['translated_description'])) {
            $repo->translate($article, 'lead', $locale, $translationData['translated_description']);
        }

        if (!empty($translationData['seo_description'])) {
            $repo->translate($article, 'metaDescription', $locale, mb_substr($translationData['seo_description'], 0, 160));
        }

        if (!empty($translationData['suggested_slug'])) {
            $repo->translate($article, 'slug', $locale, $translationData['suggested_slug']);
        }

        $this->em->flush();
    }

    private function callGemini(string $prompt): ?string
    {
        $process = new Process([$this->geminiCliPath, '-p', $prompt]);
        $process->setTimeout(self::TIMEOUT);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger->warning('TranslationEvaluator: Gemini process failed', [
                    'exitCode' => $process->getExitCode(),
                    'error' => mb_substr($process->getErrorOutput(), 0, 200),
                ]);

                return null;
            }

            return trim($process->getOutput());
        } catch (\Throwable $e) {
            $this->logger->error('TranslationEvaluator: exception calling Gemini', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function parseEvaluationResponse(string $raw): TranslationEvaluationResult
    {
        // Strip markdown code block wrappers
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $cleaned = preg_replace('/\s*```\s*$/m', '', $cleaned ?? $raw);
        $cleaned = trim($cleaned ?? $raw);

        // Handle Gemini CLI envelope { session_id, response, stats }
        $decoded = json_decode($cleaned, true);

        if (\is_array($decoded) && isset($decoded['response']) && \is_string($decoded['response'])) {
            $inner = $decoded['response'];
            $inner = preg_replace('/^```(?:json)?\s*/m', '', $inner);
            $inner = preg_replace('/\s*```\s*$/m', '', $inner ?? $decoded['response']);
            $cleaned = trim($inner ?? $decoded['response']);
            $decoded = json_decode($cleaned, true);
        }

        // Extract JSON from preamble if Gemini prepended text
        if (!\is_array($decoded)) {
            $jsonStart = strpos($cleaned, '{');
            $jsonEnd = strrpos($cleaned, '}');
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $cleaned = substr($cleaned, $jsonStart, $jsonEnd - $jsonStart + 1);
                $decoded = json_decode($cleaned, true);
            }
        }

        if (!\is_array($decoded) || !isset($decoded['score'])) {
            $this->logger->warning('TranslationEvaluator: failed to parse evaluation JSON', [
                'raw' => mb_substr($raw, 0, 300),
            ]);

            return new TranslationEvaluationResult(
                score: 0.0,
                issues: [['type' => 'system', 'severity' => 'critical', 'description' => 'Failed to parse evaluation response']],
                needsRevision: true,
                revisionInstructions: null,
            );
        }

        $score = (float) ($decoded['score'] ?? 0.0);
        $issues = [];

        if (isset($decoded['issues']) && \is_array($decoded['issues'])) {
            foreach ($decoded['issues'] as $issue) {
                if (\is_array($issue)) {
                    $issues[] = [
                        'type' => (string) ($issue['type'] ?? 'unknown'),
                        'severity' => (string) ($issue['severity'] ?? 'minor'),
                        'description' => (string) ($issue['description'] ?? ''),
                    ];
                }
            }
        }

        $needsRevision = (bool) ($decoded['needs_revision'] ?? ($score < self::SCORE_THRESHOLD));
        $revisionInstructions = isset($decoded['revision_instructions']) && \is_string($decoded['revision_instructions'])
            ? $decoded['revision_instructions']
            : null;

        return new TranslationEvaluationResult($score, $issues, $needsRevision, $revisionInstructions);
    }
}
