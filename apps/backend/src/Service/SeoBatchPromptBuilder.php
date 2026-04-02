<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Repository\TagRepository;

/**
 * Builds Gemini prompts for batch SEO optimization (multiple articles at once).
 */
final readonly class SeoBatchPromptBuilder
{
    private const MAX_CONTENT_PER_ARTICLE = 500;

    public function __construct(
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * Build a batch prompt for multiple articles.
     *
     * @param Article[] $articles
     * @param array{generateMeta: bool, suggestTags: bool, force: bool} $options
     */
    public function build(array $articles, array $options): string
    {
        $count = \count($articles);

        // Get existing tag names (once for the entire batch)
        $existingTags = [];
        if ($options['suggestTags']) {
            $allTags = $this->tagRepository->findPopularTags(200, 'ro');
            foreach ($allTags as $tag) {
                $existingTags[] = $tag->getName();
            }
        }

        $prompt = "Ești un expert SEO pentru un portal de știri din Republica Moldova (deschide.md).\n";
        $prompt .= "Limba: română.\n\n";
        $prompt .= "Analizează cele {$count} articole de mai jos și generează pentru FIECARE:\n";

        if ($options['generateMeta']) {
            $prompt .= "1. metaTitle — titlu SEO optimizat, MAXIM 60 caractere\n";
            $prompt .= "2. metaDescription — descriere SEO optimizată, MAXIM 160 caractere\n";
        }
        if ($options['suggestTags']) {
            $prompt .= "3. suggestedTags — 3-5 tag-uri relevante din lista existentă SAU noi\n";
        }

        if ($options['suggestTags'] && $existingTags !== []) {
            $prompt .= "\nTAG-URI EXISTENTE (folosește cu prioritate):\n";
            $prompt .= implode(', ', $existingTags) . "\n";
        }

        $prompt .= "\nARTICOLE:\n";

        foreach ($articles as $article) {
            $title = $article->getTitle() ?? '';
            $categoryName = $article->getCategory()?->getTitle() ?? '';
            $lead = $article->getLead();
            $content = $article->getContent() ?? '';

            $leadStripped = $lead !== null ? strip_tags($lead) : '';
            $contentStripped = strip_tags($content);
            if (mb_strlen($contentStripped) > self::MAX_CONTENT_PER_ARTICLE) {
                $contentStripped = mb_substr($contentStripped, 0, self::MAX_CONTENT_PER_ARTICLE);
            }

            $prompt .= "\n---\n";
            $prompt .= "articleId: {$article->getId()}\n";
            $prompt .= "Titlu: {$title}\n";
            if ($categoryName !== '') {
                $prompt .= "Categorie: {$categoryName}\n";
            }
            if ($leadStripped !== '') {
                $prompt .= "Lead: {$leadStripped}\n";
            }
            $prompt .= "Conținut: {$contentStripped}\n";
        }

        $prompt .= "\nREGULI:\n";
        if ($options['generateMeta']) {
            $prompt .= "- metaTitle: STRICT maxim 60 caractere per articol. Dacă depășește, reformulează.\n";
            $prompt .= "- metaDescription: STRICT maxim 160 caractere per articol. Dacă depășește, reformulează.\n";
        }
        if ($options['suggestTags']) {
            $prompt .= "- Folosește tag-uri existente cu prioritate. Propune noi DOAR dacă necesar.\n";
            $prompt .= "- Tag-urile trebuie să fie substantive sau sintagme scurte.\n";
        }
        $prompt .= "- Folosește diacritice românești corecte (ș cu virgulă, NU ş cu sedilă).\n";
        $prompt .= "- Răspunde DOAR cu JSON valid, fără text adițional.\n";
        $prompt .= "- Returnează un JSON array cu EXACT {$count} elemente, câte unul per articol.\n";

        $prompt .= "\nFORMAT RĂSPUNS (JSON array strict):\n[\n  {\n";
        $prompt .= "    \"articleId\": 123,\n";
        if ($options['generateMeta']) {
            $prompt .= "    \"metaTitle\": \"...\",\n";
            $prompt .= "    \"metaDescription\": \"...\",\n";
        }
        if ($options['suggestTags']) {
            $prompt .= "    \"suggestedTags\": [{\"name\": \"Tag\", \"isNew\": false}]\n";
        }
        $prompt .= "  }\n]\n";

        return $prompt;
    }
}
