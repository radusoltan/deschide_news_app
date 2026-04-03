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

    /** @var array<string, string> MOC mapping: category title (lowercase) → MOC filename */
    private const MOC_MAPPING = [
        'politică' => 'MOC-Politica-Interna.md',
        'politica' => 'MOC-Politica-Interna.md',
        'economie' => 'MOC-Economie.md',
        'integrare-ue' => 'MOC-Integrare-UE.md',
        'justiție' => 'MOC-Justitie.md',
        'justitie' => 'MOC-Justitie.md',
        'transnistria' => 'MOC-Transnistria.md',
        'externe' => 'MOC-Relatii-Externe.md',
        'extern' => 'MOC-Relatii-Externe.md',
        'societate' => 'MOC-Societate.md',
        'sport' => 'MOC-Sport.md',
        'cultură' => 'MOC-Cultura.md',
        'cultura' => 'MOC-Cultura.md',
        'energie' => 'MOC-Energie.md',
        'editoriale' => 'MOC-Politica-Interna.md',
        'opinii' => 'MOC-Politica-Interna.md',
        'românia' => 'MOC-Politica-Interna.md',
        'romania' => 'MOC-Politica-Interna.md',
        'anti-fake' => 'MOC-Societate.md',
        'advertorial' => 'MOC-Economie.md',
    ];

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
     * Create atomic notes in Obsidian vault for new entities.
     * Uses file system writes to the vault path.
     *
     * @param string $vaultPath Path to Obsidian vault root
     */
    public function createAtomicNotes(
        EntityExtractionResult $result,
        Article $article,
        string $vaultPath,
    ): int {
        if (!$result->hasEntities() || $vaultPath === '') {
            return 0;
        }

        $created = 0;
        $articleRef = $this->buildArticleReference($article);

        // Create person notes
        foreach ($result->persons as $person) {
            $slug = $this->slugify($person['name']);
            $path = "{$vaultPath}/knowledge/persons/PER-{$slug}.md";

            if (file_exists($path)) {
                $this->appendBacklink($path, $articleRef);

                continue;
            }

            $content = $this->buildPersonNote($person, $articleRef);
            if ($this->writeNote($path, $content)) {
                $created++;
            }
        }

        // Create institution notes
        foreach ($result->institutions as $inst) {
            $slug = $this->slugify($inst['name']);
            $path = "{$vaultPath}/knowledge/institutions/INST-{$slug}.md";

            if (file_exists($path)) {
                $this->appendBacklink($path, $articleRef);

                continue;
            }

            $content = $this->buildInstitutionNote($inst, $articleRef);
            if ($this->writeNote($path, $content)) {
                $created++;
            }
        }

        // Create event notes
        foreach ($result->events as $event) {
            $date = $event['date'] ?? date('Y-m-d');
            $slug = $this->slugify($event['name']);
            $path = "{$vaultPath}/knowledge/events/EVT-{$date}-{$slug}.md";

            if (file_exists($path)) {
                $this->appendBacklink($path, $articleRef);

                continue;
            }

            $content = $this->buildEventNote($event, $articleRef);
            if ($this->writeNote($path, $content)) {
                $created++;
            }
        }

        $this->logger->info('ArticleIngestion: atomic notes created', [
            'articleId' => $article->getId(),
            'created' => $created,
            'existing_updated' => $result->totalCount() - $created,
        ]);

        return $created;
    }

    /**
     * Update MOC files with article reference based on category/topics.
     *
     * @param string $vaultPath Path to Obsidian vault root
     */
    public function updateMOCs(Article $article, EntityExtractionResult $result, string $vaultPath): int
    {
        if ($vaultPath === '') {
            return 0;
        }

        $updated = 0;
        $articleRef = $this->buildArticleReference($article);
        $mocsToUpdate = $this->resolveMOCs($article, $result);

        foreach ($mocsToUpdate as $mocFile) {
            $mocPath = "{$vaultPath}/mocs/{$mocFile}";

            if (!file_exists($mocPath)) {
                continue;
            }

            $content = file_get_contents($mocPath);
            if ($content === false) {
                continue;
            }

            // Check if article is already linked
            if (str_contains($content, $articleRef)) {
                continue;
            }

            // Append to chronology section
            $date = $article->getPublishedAt()?->format('Y-m-d') ?? date('Y-m-d');
            $entry = "\n- {$date} — {$articleRef}";

            if (str_contains($content, '## Cronologie')) {
                $content = preg_replace(
                    '/(## Cronologie[^\n]*\n)/',
                    "$1{$entry}\n",
                    $content,
                    1,
                );
            } else {
                $content .= "\n\n## Cronologie\n{$entry}\n";
            }

            if (file_put_contents($mocPath, $content) !== false) {
                $updated++;
            }
        }

        if ($updated > 0) {
            $this->logger->info('ArticleIngestion: MOCs updated', [
                'articleId' => $article->getId(),
                'mocsUpdated' => $updated,
            ]);
        }

        return $updated;
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

    private function buildArticleReference(Article $article): string
    {
        $slug = $article->getSlug() ?? 'article-' . $article->getId();
        $date = $article->getPublishedAt()?->format('Y-m-d') ?? date('Y-m-d');

        return "[[art-{$date}-{$slug}]]";
    }

    /**
     * @param array{name: string, role?: string, institution?: string} $person
     */
    private function buildPersonNote(array $person, string $articleRef): string
    {
        $name = $person['name'];
        $role = $person['role'] ?? '';
        $institution = $person['institution'] ?? '';
        $date = date('Y-m-d');

        $frontmatter = [
            'type' => 'person',
            'name' => $name,
            'role' => $role,
            'institution' => $institution,
            'date_created' => $date,
            'ai' => ['auto_generated' => true, 'reviewed' => false],
        ];

        return "---\n" . $this->toYaml($frontmatter) . "---\n\n"
            . "# {$name}\n\n"
            . ($role !== '' ? "**Funcție**: {$role}\n" : '')
            . ($institution !== '' ? "**Instituție**: {$institution}\n" : '')
            . "\n## Menționări\n\n- {$articleRef}\n";
    }

    /**
     * @param array{name: string, abbreviation?: string, type?: string} $inst
     */
    private function buildInstitutionNote(array $inst, string $articleRef): string
    {
        $name = $inst['name'];
        $abbr = $inst['abbreviation'] ?? '';
        $type = $inst['type'] ?? '';
        $date = date('Y-m-d');

        $frontmatter = [
            'type' => 'institution',
            'name' => $name,
            'abbreviation' => $abbr,
            'institution_type' => $type,
            'date_created' => $date,
            'ai' => ['auto_generated' => true, 'reviewed' => false],
        ];

        return "---\n" . $this->toYaml($frontmatter) . "---\n\n"
            . "# {$name}" . ($abbr !== '' ? " ({$abbr})" : '') . "\n\n"
            . ($type !== '' ? "**Tip**: {$type}\n" : '')
            . "\n## Menționări\n\n- {$articleRef}\n";
    }

    /**
     * @param array{name: string, date?: string, location?: string} $event
     */
    private function buildEventNote(array $event, string $articleRef): string
    {
        $name = $event['name'];
        $eventDate = $event['date'] ?? date('Y-m-d');
        $location = $event['location'] ?? '';
        $date = date('Y-m-d');

        $frontmatter = [
            'type' => 'event',
            'name' => $name,
            'event_date' => $eventDate,
            'location' => $location,
            'date_created' => $date,
            'ai' => ['auto_generated' => true, 'reviewed' => false],
        ];

        return "---\n" . $this->toYaml($frontmatter) . "---\n\n"
            . "# {$name}\n\n"
            . "**Data**: {$eventDate}\n"
            . ($location !== '' ? "**Locație**: {$location}\n" : '')
            . "\n## Surse\n\n- {$articleRef}\n";
    }

    private function appendBacklink(string $filePath, string $articleRef): void
    {
        $content = file_get_contents($filePath);
        if ($content === false || str_contains($content, $articleRef)) {
            return;
        }

        // Append to Menționări or Surse section
        if (str_contains($content, '## Menționări')) {
            $content = preg_replace(
                '/(## Menționări\s*\n)/',
                "$1- {$articleRef}\n",
                $content,
                1,
            );
        } elseif (str_contains($content, '## Surse')) {
            $content = preg_replace(
                '/(## Surse\s*\n)/',
                "$1- {$articleRef}\n",
                $content,
                1,
            );
        } else {
            $content .= "\n\n## Menționări\n\n- {$articleRef}\n";
        }

        file_put_contents($filePath, $content);
    }

    private function writeNote(string $path, string $content): bool
    {
        $dir = \dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0o755, true)) {
            $this->logger->warning('ArticleIngestion: cannot create directory', ['path' => $dir]);

            return false;
        }

        if (file_put_contents($path, $content) === false) {
            $this->logger->warning('ArticleIngestion: cannot write note', ['path' => $path]);

            return false;
        }

        return true;
    }

    /**
     * Resolve which MOC files should be updated for this article.
     *
     * @return list<string>
     */
    private function resolveMOCs(Article $article, EntityExtractionResult $result): array
    {
        $mocs = [];

        // From article category
        $catName = $article->getCategory()?->getTitle();
        if ($catName !== null) {
            $catSlug = mb_strtolower($catName);
            if (isset(self::MOC_MAPPING[$catSlug])) {
                $mocs[] = self::MOC_MAPPING[$catSlug];
            }
        }

        // From suggested categories
        foreach ($result->categoriesSuggested as $cat) {
            $catSlug = mb_strtolower($cat);
            if (isset(self::MOC_MAPPING[$catSlug]) && !\in_array(self::MOC_MAPPING[$catSlug], $mocs, true)) {
                $mocs[] = self::MOC_MAPPING[$catSlug];
            }
        }

        return $mocs;
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

    /**
     * Simple YAML serialization for frontmatter.
     *
     * @param array<string, mixed> $data
     */
    private function toYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value) && !array_is_list($value)) {
                $yaml .= "{$prefix}{$key}:\n" . $this->toYaml($value, $indent + 1);
            } elseif (is_array($value)) {
                $yaml .= "{$prefix}{$key}: [" . implode(', ', array_map(fn ($v) => is_string($v) ? "\"{$v}\"" : (string) $v, $value)) . "]\n";
            } elseif (is_bool($value)) {
                $yaml .= "{$prefix}{$key}: " . ($value ? 'true' : 'false') . "\n";
            } elseif (is_int($value) || is_float($value)) {
                $yaml .= "{$prefix}{$key}: {$value}\n";
            } else {
                $val = (string) $value;
                if ($val === '' || preg_match('/[:#{}[\],&*?|>!%@`]/', $val)) {
                    $yaml .= "{$prefix}{$key}: \"{$val}\"\n";
                } else {
                    $yaml .= "{$prefix}{$key}: {$val}\n";
                }
            }
        }

        return $yaml;
    }
}
