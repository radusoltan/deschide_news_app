<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Article;
use App\Repository\TagRepository;

final readonly class SeoPromptBuilder
{
    private const MAX_CONTENT_LENGTH = 2000;

    public function __construct(
        private TagRepository $tagRepository,
    ) {
    }

    /**
     * Build the Gemini prompt for SEO optimization.
     *
     * @param array{generateMeta: bool, suggestTags: bool, force: bool} $options
     */
    public function build(Article $article, array $options): string
    {
        $title = $article->getTitle() ?? '';
        $lead = $article->getLead();
        $content = $article->getContent() ?? '';
        $categoryName = $article->getCategory()?->getTitle() ?? '';

        // Strip HTML and truncate content
        $contentStripped = strip_tags($content);
        if (mb_strlen($contentStripped) > self::MAX_CONTENT_LENGTH) {
            $contentStripped = mb_substr($contentStripped, 0, self::MAX_CONTENT_LENGTH);
        }

        // Strip HTML from lead if present
        $leadStripped = $lead !== null ? strip_tags($lead) : null;

        // Get all existing tag names for the prompt
        $existingTags = [];
        if ($options['suggestTags']) {
            $allTags = $this->tagRepository->findPopularTags(200, 'ro');
            foreach ($allTags as $tag) {
                $existingTags[] = $tag->getName();
            }
        }

        // Determine whether to skip meta generation
        $skipMeta = !$options['generateMeta']
            || (!$options['force'] && $article->getMetaTitle() !== null && $article->getMetaDescription() !== null);

        $prompt = "Ești un expert SEO pentru un portal de știri din Republica Moldova (deschide.md).\n";
        $prompt .= "Limba articolului: română.\n";
        $prompt .= "Analizează articolul de mai jos și generează:\n\n";

        if (!$skipMeta) {
            $prompt .= "metaTitle — titlu SEO optimizat, MAXIM 60 caractere. Trebuie să conțină cuvintele cheie principale. NU copia titlul articolului — reformulează pentru click-through rate maxim.\n";
            $prompt .= "metaDescription — descriere SEO optimizată, MAXIM 160 caractere. Trebuie să fie un rezumat atractiv care invită la click. NU copia lead-ul — rescrie pentru SEO.\n";
        }

        if ($options['suggestTags']) {
            $prompt .= "suggestedTags — 3 până la 5 tag-uri relevante. Prioritizează tag-urile din lista existentă. Propune tag-uri noi DOAR dacă nu există echivalent.\n";
        }

        if ($options['suggestTags'] && $existingTags !== []) {
            $prompt .= "\nTAG-URI EXISTENTE (folosește-le cu prioritate):\n";
            $prompt .= implode(', ', $existingTags) . "\n";
        }

        $prompt .= "\nARTICOL:\n";
        $prompt .= "Titlu: {$title}\n";

        if ($categoryName !== '') {
            $prompt .= "Categorie: {$categoryName}\n";
        }

        if ($leadStripped !== null && $leadStripped !== '') {
            $prompt .= "Lead: {$leadStripped}\n";
        }

        $prompt .= "Conținut (primele 2000 caractere): {$contentStripped}\n";

        $prompt .= "\nREGULI:\n";

        if (!$skipMeta) {
            $prompt .= "- metaTitle: STRICT maxim 60 caractere. Dacă depășește, reformulează.\n";
            $prompt .= "- metaDescription: STRICT maxim 160 caractere. Dacă depășește, reformulează.\n";
        }

        if ($options['suggestTags']) {
            $prompt .= "- Tag-urile sugerate trebuie să fie substantive sau sintagme scurte, nu propoziții.\n";
        }

        $prompt .= "- Folosește diacritice românești corecte (ș cu virgulă, NU ş cu sedilă).\n";
        $prompt .= "- Răspunde DOAR cu JSON valid, fără text adițional.\n";

        // Build format example
        $prompt .= "\nFORMAT RĂSPUNS (JSON strict):\n{\n";

        if (!$skipMeta) {
            $prompt .= "  \"metaTitle\": \"...\",\n";
            $prompt .= "  \"metaDescription\": \"...\",\n";
        }

        if ($options['suggestTags']) {
            $prompt .= "  \"suggestedTags\": [\n";
            $prompt .= "    {\"name\": \"Tag Existent\", \"isNew\": false},\n";
            $prompt .= "    {\"name\": \"Tag Nou Propus\", \"isNew\": true}\n";
            $prompt .= "  ]\n";
        }

        $prompt .= "}\n";

        return $prompt;
    }
}
