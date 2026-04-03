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

    private const LOCALE_CONFIG = [
        'ro' => [
            'name' => 'română',
            'instruction' => "Ești un expert SEO pentru un portal de știri din Republica Moldova (deschide.md).",
            'metaTitle' => "metaTitle — titlu SEO optimizat, MAXIM 60 caractere. Trebuie să conțină cuvintele cheie principale. NU copia titlul articolului — reformulează pentru click-through rate maxim.",
            'metaDesc' => "metaDescription — descriere SEO optimizată, MAXIM 160 caractere. Trebuie să fie un rezumat atractiv care invită la click. NU copia lead-ul — rescrie pentru SEO.",
            'tags' => "suggestedTags — 3 până la 5 tag-uri relevante. Prioritizează tag-urile din lista existentă. Propune tag-uri noi DOAR dacă nu există echivalent.",
            'diacritics' => "- Folosește diacritice românești corecte (ș cu virgulă, NU ş cu sedilă).",
            'jsonOnly' => "- Răspunde DOAR cu JSON valid, fără text adițional.",
            'tagRule' => "- Tag-urile sugerate trebuie să fie substantive sau sintagme scurte, nu propoziții.",
        ],
        'en' => [
            'name' => 'English',
            'instruction' => "You are an SEO expert for a Moldovan news portal (deschide.md).",
            'metaTitle' => "metaTitle — SEO-optimized title, MAX 60 characters. Must contain the main keywords. Do NOT copy the article title — rephrase for maximum click-through rate.",
            'metaDesc' => "metaDescription — SEO-optimized description, MAX 160 characters. Must be an attractive summary that invites clicks. Do NOT copy the lead — rewrite for SEO.",
            'tags' => "suggestedTags — 3 to 5 relevant tags. Prioritize tags from the existing list. Suggest new tags ONLY if no equivalent exists.",
            'diacritics' => "",
            'jsonOnly' => "- Respond ONLY with valid JSON, no additional text.",
            'tagRule' => "- Suggested tags must be nouns or short phrases, not sentences.",
        ],
        'ru' => [
            'name' => 'русский',
            'instruction' => "Вы эксперт по SEO для молдавского новостного портала (deschide.md).",
            'metaTitle' => "metaTitle — оптимизированный для SEO заголовок, МАКСИМУМ 60 символов. Должен содержать основные ключевые слова. НЕ копируйте заголовок статьи — перефразируйте для максимального CTR.",
            'metaDesc' => "metaDescription — оптимизированное для SEO описание, МАКСИМУМ 160 символов. Должно быть привлекательным резюме, побуждающим к клику. НЕ копируйте лид — перепишите для SEO.",
            'tags' => "suggestedTags — от 3 до 5 релевантных тегов. Приоритизируйте теги из существующего списка. Предлагайте новые теги ТОЛЬКО если нет эквивалента.",
            'diacritics' => "",
            'jsonOnly' => "- Отвечайте ТОЛЬКО валидным JSON, без дополнительного текста.",
            'tagRule' => "- Предложенные теги должны быть существительными или короткими фразами, а не предложениями.",
        ],
    ];

    /**
     * Build the Gemini prompt for SEO optimization.
     *
     * @param array{generateMeta: bool, suggestTags: bool, force: bool} $options
     */
    public function build(Article $article, array $options, string $locale = 'ro'): string
    {
        $cfg = self::LOCALE_CONFIG[$locale] ?? self::LOCALE_CONFIG['ro'];

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
            $allTags = $this->tagRepository->findPopularTags(200, $locale);
            foreach ($allTags as $tag) {
                $existingTags[] = $tag->getName();
            }
        }

        // Determine whether to skip meta generation
        $skipMeta = !$options['generateMeta']
            || (!$options['force'] && $article->getMetaTitle() !== null && $article->getMetaDescription() !== null);

        $prompt = $cfg['instruction'] . "\n";
        $prompt .= ($locale === 'ro' ? "Limba articolului: {$cfg['name']}." : "Article language: {$cfg['name']}.") . "\n";
        $prompt .= ($locale === 'ru' ? "Проанализируйте статью ниже и сгенерируйте:" : ($locale === 'en' ? "Analyze the article below and generate:" : "Analizează articolul de mai jos și generează:")) . "\n\n";

        if (!$skipMeta) {
            $prompt .= $cfg['metaTitle'] . "\n";
            $prompt .= $cfg['metaDesc'] . "\n";
        }

        if ($options['suggestTags']) {
            $prompt .= $cfg['tags'] . "\n";
        }

        if ($options['suggestTags'] && $existingTags !== []) {
            $label = $locale === 'ru' ? 'СУЩЕСТВУЮЩИЕ ТЕГИ (используйте их с приоритетом)' : ($locale === 'en' ? 'EXISTING TAGS (use with priority)' : 'TAG-URI EXISTENTE (folosește-le cu prioritate)');
            $prompt .= "\n{$label}:\n";
            $prompt .= implode(', ', $existingTags) . "\n";
        }

        $articleLabel = $locale === 'ru' ? 'СТАТЬЯ' : ($locale === 'en' ? 'ARTICLE' : 'ARTICOL');
        $prompt .= "\n{$articleLabel}:\n";
        $titleLabel = $locale === 'ru' ? 'Заголовок' : ($locale === 'en' ? 'Title' : 'Titlu');
        $prompt .= "{$titleLabel}: {$title}\n";

        if ($categoryName !== '') {
            $catLabel = $locale === 'ru' ? 'Категория' : ($locale === 'en' ? 'Category' : 'Categorie');
            $prompt .= "{$catLabel}: {$categoryName}\n";
        }

        if ($leadStripped !== null && $leadStripped !== '') {
            $prompt .= "Lead: {$leadStripped}\n";
        }

        $contentLabel = $locale === 'ru' ? 'Содержание (первые 2000 символов)' : ($locale === 'en' ? 'Content (first 2000 characters)' : 'Conținut (primele 2000 caractere)');
        $prompt .= "{$contentLabel}: {$contentStripped}\n";

        $rulesLabel = $locale === 'ru' ? 'ПРАВИЛА' : ($locale === 'en' ? 'RULES' : 'REGULI');
        $prompt .= "\n{$rulesLabel}:\n";

        if (!$skipMeta) {
            $prompt .= "- metaTitle: " . ($locale === 'ru' ? 'СТРОГО максимум 60 символов.' : ($locale === 'en' ? 'STRICTLY max 60 characters.' : 'STRICT maxim 60 caractere. Dacă depășește, reformulează.')) . "\n";
            $prompt .= "- metaDescription: " . ($locale === 'ru' ? 'СТРОГО максимум 160 символов.' : ($locale === 'en' ? 'STRICTLY max 160 characters.' : 'STRICT maxim 160 caractere. Dacă depășește, reformulează.')) . "\n";
        }

        if ($options['suggestTags']) {
            $prompt .= $cfg['tagRule'] . "\n";
        }

        if ($cfg['diacritics'] !== '') {
            $prompt .= $cfg['diacritics'] . "\n";
        }
        $prompt .= $cfg['jsonOnly'] . "\n";

        // Build format example
        $formatLabel = $locale === 'ru' ? 'ФОРМАТ ОТВЕТА (строгий JSON)' : ($locale === 'en' ? 'RESPONSE FORMAT (strict JSON)' : 'FORMAT RĂSPUNS (JSON strict)');
        $prompt .= "\n{$formatLabel}:\n{\n";

        if (!$skipMeta) {
            $prompt .= "  \"metaTitle\": \"...\",\n";
            $prompt .= "  \"metaDescription\": \"...\",\n";
        }

        if ($options['suggestTags']) {
            $prompt .= "  \"suggestedTags\": [\n";
            $prompt .= "    {\"name\": \"Existing Tag\", \"isNew\": false},\n";
            $prompt .= "    {\"name\": \"New Suggested Tag\", \"isNew\": true}\n";
            $prompt .= "  ]\n";
        }

        $prompt .= "}\n";

        return $prompt;
    }
}
