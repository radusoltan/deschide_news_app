<?php

declare(strict_types=1);

namespace App\Command\Fixtures;

use App\Service\Ai\Provider\GeminiCliProvider;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Generates EN translations for the top N articles per category via Gemini CLI.
 * Designed for app:dev:reset enrichment — not for production translation pipeline.
 */
#[AsCommand(
    name: 'app:fixtures:generate-translations',
    description: 'Generate EN translations for top N articles per category via Gemini CLI',
)]
final class GenerateTranslationsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GeminiCliProvider $geminiProvider,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('top-per-category', null, InputOption::VALUE_REQUIRED, 'Number of articles to translate per category', '10')
            ->addOption('source-locale', null, InputOption::VALUE_REQUIRED, 'Source locale', 'ro')
            ->addOption('target-locale', null, InputOption::VALUE_REQUIRED, 'Target locale', 'en')
            ->addOption('resume', null, InputOption::VALUE_NONE, 'Skip articles that already have the target locale translation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $topN = (int) $input->getOption('top-per-category');
        $sourceLoc = $input->getOption('source-locale');
        $targetLoc = $input->getOption('target-locale');
        $resume = $input->getOption('resume');

        /** @var Connection $conn */
        $conn = $this->em->getConnection();
        $translationRepo = $this->em->getRepository(Translation::class);

        $io->title("Generate {$targetLoc} translations (top {$topN}/category, source: {$sourceLoc})");

        // Get category IDs
        $categories = $conn->fetchAllAssociative(
            'SELECT id, slug FROM categories ORDER BY front_page_position',
        );

        $translated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($categories as $cat) {
            // Top N most recent published articles per category
            $articles = $conn->fetchAllAssociative(
                "SELECT a.id, a.title, a.lead, a.content
                 FROM articles a
                 WHERE a.category_id = ? AND a.status = 'published'
                 ORDER BY a.published_at DESC NULLS LAST, a.id DESC
                 LIMIT ?",
                [$cat['id'], $topN],
            );

            foreach ($articles as $article) {
                // Skip if target locale already exists (idempotent)
                if ($resume) {
                    $existing = $conn->fetchOne(
                        "SELECT COUNT(*) FROM ext_translations
                         WHERE object_class = 'App\\Entity\\Article'
                           AND foreign_key = ?
                           AND locale = ?
                           AND field = 'title'",
                        [(string) $article['id'], $targetLoc],
                    );
                    if ((int) $existing > 0) {
                        $skipped++;
                        continue;
                    }
                }

                try {
                    $result = $this->translateArticle(
                        $article['title'] ?? '',
                        $article['lead'] ?? '',
                        $article['content'] ?? '',
                        $sourceLoc,
                        $targetLoc,
                    );

                    // Store translations
                    $articleEntity = $this->em->find(\App\Entity\Article::class, $article['id']);
                    if ($articleEntity === null) {
                        $failed++;
                        continue;
                    }

                    if (isset($result['title']) && $result['title'] !== '') {
                        $translationRepo->translate($articleEntity, 'title', $targetLoc, $result['title']);
                    }
                    if (isset($result['lead']) && $result['lead'] !== '') {
                        $translationRepo->translate($articleEntity, 'lead', $targetLoc, $result['lead']);
                    }
                    if (isset($result['content']) && $result['content'] !== '') {
                        $translationRepo->translate($articleEntity, 'content', $targetLoc, $result['content']);
                    }

                    $this->em->flush();
                    $translated++;

                    if ($translated % 10 === 0) {
                        $io->text("  Translated {$translated} articles...");
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $io->warning("  Failed article #{$article['id']}: {$e->getMessage()}");
                }
            }
        }

        $io->success("Done: {$translated} translated, {$skipped} skipped, {$failed} failed");

        return Command::SUCCESS;
    }

    /**
     * @return array{title?: string, lead?: string, content?: string}
     */
    private function translateArticle(
        string $title,
        string $lead,
        string $content,
        string $sourceLoc,
        string $targetLoc,
    ): array {
        $prompt = <<<PROMPT
Translate the following news article from {$sourceLoc} to {$targetLoc}.
Return a JSON object with keys: "title", "lead", "content".
Keep the HTML structure in content. Do not add commentary.

Title: {$title}

Lead: {$lead}

Content: {$content}
PROMPT;

        $response = $this->geminiProvider->chat($prompt);

        try {
            $json = json_decode(
                $this->extractJson($response),
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );

            return [
                'title' => $json['title'] ?? '',
                'lead' => $json['lead'] ?? '',
                'content' => $json['content'] ?? '',
            ];
        } catch (\JsonException) {
            return ['title' => $response];
        }
    }

    private function extractJson(string $text): string
    {
        // Strip markdown code fences
        $text = preg_replace('/^```(?:json)?\s*\n?/m', '', $text) ?? $text;
        $text = preg_replace('/\n?```\s*$/m', '', $text) ?? $text;

        // Find JSON object
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        if ($start !== false && $end !== false && $end > $start) {
            return substr($text, $start, $end - $start + 1);
        }

        return $text;
    }
}
