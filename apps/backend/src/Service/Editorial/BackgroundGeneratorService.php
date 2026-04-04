<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Dto\Editorial\BackgroundResult;
use App\Dto\Editorial\ContextData;
use App\Entity\Article;
use App\Service\Search\SearchService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final class BackgroundGeneratorService
{
    private const GEMINI_TIMEOUT = 60;
    private const MAX_ES_RESULTS = 5;

    public function __construct(
        private readonly SearchService $searchService,
        private readonly string $geminiCliPath,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Generează un paragraf de background/context istoric pentru un articol.
     */
    public function generateBackground(Article $article): ?BackgroundResult
    {
        $context = $this->gatherContext($article);

        if ($context->isEmpty()) {
            $this->logger->info('BackgroundGenerator: no context found', [
                'articleId' => $article->getId(),
            ]);

            return null;
        }

        return $this->synthesize($article, $context);
    }

    /**
     * Colectează context din Elasticsearch.
     */
    private function gatherContext(Article $article): ContextData
    {
        $contextData = new ContextData();

        // SURSA: Articole anterioare din Elasticsearch
        $this->gatherFromElasticsearch($article, $contextData);

        return $contextData;
    }

    private function gatherFromElasticsearch(Article $article, ContextData $contextData): void
    {
        $title = $article->getTitle() ?? '';
        $categoryTitle = $article->getCategory()?->getTitle() ?? '';

        $searchTerms = array_filter([$title, $categoryTitle]);
        if ($searchTerms === []) {
            return;
        }

        // Use article title as search query for similar articles
        $searchQuery = mb_substr($title, 0, 100);

        try {
            $results = $this->searchService->search($searchQuery, 'ro', 1, self::MAX_ES_RESULTS + 1);

            // Filter out the current article
            $filtered = array_filter(
                $results['hits'],
                fn (array $hit): bool => $hit['id'] !== $article->getId(),
            );

            $contextData->previousArticles = array_slice(array_values($filtered), 0, self::MAX_ES_RESULTS);
        } catch (\Throwable $e) {
            $this->logger->warning('BackgroundGenerator: ES search failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function synthesize(Article $article, ContextData $context): ?BackgroundResult
    {
        $prompt = $this->buildSynthesisPrompt($article, $context);
        $output = $this->callGemini($prompt);

        if ($output === null) {
            return null;
        }

        // Strip markdown code blocks
        $text = preg_replace('/^```(?:\w+)?\s*/m', '', $output);
        $text = preg_replace('/\s*```\s*$/m', '', $text);
        $text = trim($text);

        if (mb_strlen($text) < 50) {
            $this->logger->warning('BackgroundGenerator: output too short', [
                'articleId' => $article->getId(),
                'length' => mb_strlen($text),
            ]);

            return null;
        }

        $referencedIds = array_map(
            fn (array $hit): int => $hit['id'],
            $context->previousArticles,
        );

        return new BackgroundResult(
            backgroundText: $text,
            referencedArticles: $referencedIds,
            mocUsed: $context->mocName,
            sourcesCount: $context->sourcesCount(),
        );
    }

    private function buildSynthesisPrompt(Article $article, ContextData $context): string
    {
        $title = $article->getTitle() ?? '';
        $categoryTitle = $article->getCategory()?->getTitle() ?? '';

        // Previous articles
        $previousTitles = [];
        foreach ($context->previousArticles as $hit) {
            $src = $hit['source'] ?? [];
            $artTitle = $src['title_ro'] ?? $src['title_en'] ?? 'N/A';
            $date = $src['date_published'] ?? '';
            $previousTitles[] = "- {$artTitle} ({$date})";
        }
        $previousSection = $previousTitles !== []
            ? implode("\n", $previousTitles)
            : 'Nu există articole anterioare relevante.';

        return <<<PROMPT
Ești un editor senior la portalul Deschide News din Republica Moldova.
Generează un paragraf de CONTEXT ISTORIC (background) pentru un articol de știri.

Articolul curent:
Titlu: {$title}
Categorie: {$categoryTitle}

Articole anterioare pe același subiect:
{$previousSection}

Reguli:
- Exact 1 paragraf, 3-5 propoziții
- Începe cu "Amintim că..." sau "Contextul acestei știri..." sau altă formulare naturală
- Menționează doar fapte verificabile din sursele de mai sus
- NU inventa informații
- Limba: română cu diacritice comma-below (ș, ț)
- Ton: neutru-jurnalistic
- Referă articolele anterioare prin titlu și dată dacă sunt relevante

Paragraf context:
PROMPT;
    }

    private function callGemini(string $prompt): ?string
    {
        $process = new Process([$this->geminiCliPath, '-p', $prompt]);
        $process->setTimeout(self::GEMINI_TIMEOUT);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger->warning('BackgroundGenerator: Gemini failed', [
                    'exitCode' => $process->getExitCode(),
                    'error' => mb_substr($process->getErrorOutput(), 0, 200),
                ]);

                return null;
            }

            return trim($process->getOutput());
        } catch (\Throwable $e) {
            $this->logger->error('BackgroundGenerator: Gemini exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
