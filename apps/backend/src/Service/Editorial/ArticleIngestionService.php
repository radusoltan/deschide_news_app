<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Dto\Editorial\EntityExtractionResult;
use App\Entity\Article;
use App\Service\NotebookLM\NotebookLMService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

final class ArticleIngestionService
{
    private const GEMINI_TIMEOUT = 120;

    public function __construct(
        private readonly string $geminiCliPath,
        private readonly NotebookLMService $notebookLMService,
        private readonly LoggerInterface $logger,
        /** @var array<string, string> */
        private readonly array $notebooks = [],
    ) {}

    /**
     * Extract entities from article content using Gemini CLI.
     */
    public function extractEntities(Article $article): EntityExtractionResult
    {
        $title = $article->getTitle() ?? '';
        $content = $article->getContent() ?? '';

        if ($title === '' && $content === '') {
            return new EntityExtractionResult();
        }

        $prompt = $this->buildExtractionPrompt($title, $content);
        $raw = $this->callGemini($prompt);

        if ($raw === null) {
            $this->logger->warning('ArticleIngestion: Gemini entity extraction failed', [
                'articleId' => $article->getId(),
            ]);

            return new EntityExtractionResult();
        }

        $parsed = $this->parseJsonResponse($raw);
        if ($parsed === null) {
            $this->logger->warning('ArticleIngestion: invalid JSON from Gemini', [
                'articleId' => $article->getId(),
                'raw' => mb_substr($raw, 0, 200),
            ]);

            return new EntityExtractionResult();
        }

        $result = EntityExtractionResult::fromArray($parsed);

        $this->logger->info('ArticleIngestion: entities extracted', [
            'articleId' => $article->getId(),
            'persons' => \count($result->persons),
            'institutions' => \count($result->institutions),
            'events' => \count($result->events),
            'locations' => \count($result->locations),
            'topics' => \count($result->topics),
        ]);

        return $result;
    }

    /**
     * Feed article to the appropriate NotebookLM notebook.
     */
    public function feedNotebookLM(Article $article): bool
    {
        if (!$this->notebookLMService->isAvailable()) {
            return false;
        }

        $category = $article->getCategory()?->getTitle() ?? '';
        $notebookId = $this->notebookLMService->resolveNotebookId($category, $this->notebooks);

        if ($notebookId === null) {
            $this->logger->debug('ArticleIngestion: no notebook mapped for category', [
                'category' => $category,
            ]);

            return false;
        }

        $title = $article->getTitle() ?? 'Untitled';
        $content = $article->getContent() ?? '';

        return $this->notebookLMService->addTextSource($notebookId, $title, $content);
    }

    /**
     * Build extraction prompt for Gemini.
     */
    private function buildExtractionPrompt(string $title, string $content): string
    {
        $truncated = mb_substr($content, 0, 25000);

        return <<<PROMPT
Analizează următorul articol de presă și extrage toate entitățile menționate.
Returnează DOAR JSON valid, fără markdown code blocks, fără explicații:
{
  "persons": [{"name": "...", "role": "...", "institution": "..."}],
  "institutions": [{"name": "...", "abbreviation": "...", "type": "..."}],
  "events": [{"name": "...", "date": "YYYY-MM-DD", "location": "..."}],
  "locations": [{"name": "...", "type": "city|country|region"}],
  "topics": ["topic1", "topic2"],
  "categories_suggested": ["politică", "economie"],
  "confidence": 0.85
}

Reguli:
- Extrage TOATE persoanele cu nume complet, rol și instituție dacă sunt menționate
- Extrage TOATE instituțiile cu abreviere și tip (gov, ngo, party, business, media, international)
- Pentru evenimente, folosește format dată ISO (YYYY-MM-DD) dacă e menționată
- topics: 2-5 teme principale din articol
- categories_suggested: sugerează 1-3 categorii din: politică, economie, societate, justiție, transnistria, integrare-ue, energie, sport, cultură, extern
- confidence: 0.0-1.0 estimarea calității extragerii
- Păstrează diacriticele comma-below (ș, ț)

Articol:
Titlu: {$title}

{$truncated}
PROMPT;
    }

    private function callGemini(string $prompt): ?string
    {
        $process = new Process([$this->geminiCliPath, '-p', $prompt]);
        $process->setTimeout(self::GEMINI_TIMEOUT);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger->warning('ArticleIngestion: Gemini process failed', [
                    'exitCode' => $process->getExitCode(),
                    'error' => mb_substr($process->getErrorOutput(), 0, 200),
                ]);

                return null;
            }

            return trim($process->getOutput());
        } catch (\Throwable $e) {
            $this->logger->error('ArticleIngestion: Gemini exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJsonResponse(string $raw): ?array
    {
        // Strip markdown code block wrappers if present
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $raw);
        $cleaned = preg_replace('/\s*```\s*$/m', '', $cleaned);
        $cleaned = trim($cleaned);

        $data = json_decode($cleaned, true);

        return is_array($data) ? $data : null;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ', ' '],
            ['a', 'a', 'i', 's', 't', 's', 't', '-'],
            $text,
        );
        $text = preg_replace('/[^a-z0-9\-]/', '', $text);
        $text = preg_replace('/-+/', '-', trim($text, '-'));

        return mb_substr($text, 0, 60);
    }
}
