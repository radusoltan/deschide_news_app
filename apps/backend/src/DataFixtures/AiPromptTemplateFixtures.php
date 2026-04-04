<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AiPromptTemplate;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class AiPromptTemplateFixtures extends Fixture implements FixtureGroupInterface
{
    private const TEMPLATES = [
        [
            'ro' => ['name' => 'Dosarul complet pe subiect', 'description' => 'Caută și sintetizează toate articolele și dosarele legate de un subiect specific.'],
            'en' => ['name' => 'Complete dossier on topic', 'description' => 'Search and synthesize all articles and dossiers related to a specific topic.'],
            'ru' => ['name' => 'Полное досье по теме', 'description' => 'Поиск и обобщение всех статей и досье, связанных с конкретной темой.'],
            'category' => 'research',
            'agentType' => 'research',
            'promptTemplate' => 'Caută în arhivă toate articolele și dosarele legate de {topic}. Sintetizează cronologic evenimentele principale, persoanele implicate și conexiunile identificate.',
            'requiredFields' => ['topic'],
            'sortOrder' => 0,
        ],
        [
            'ro' => ['name' => 'Conexiuni între entități', 'description' => 'Analizează conexiunile și relațiile dintre două entități într-o perioadă dată.'],
            'en' => ['name' => 'Connections between entities', 'description' => 'Analyze connections and relationships between two entities in a given period.'],
            'ru' => ['name' => 'Связи между сущностями', 'description' => 'Анализ связей и отношений между двумя сущностями за определённый период.'],
            'category' => 'research',
            'agentType' => 'research',
            'promptTemplate' => 'Analizează conexiunile dintre {entity1} și {entity2} din ultimele {days} zile. Include articole, mențiuni comune și contexte partajate.',
            'requiredFields' => ['entity1', 'entity2', 'days'],
            'sortOrder' => 1,
        ],
        [
            'ro' => ['name' => 'Rescrie în ton editorial', 'description' => 'Rescrie un articol existent într-un ton editorial specificat.'],
            'en' => ['name' => 'Rewrite in editorial tone', 'description' => 'Rewrite an existing article in a specified editorial tone.'],
            'ru' => ['name' => 'Перепишите в редакционном тоне', 'description' => 'Перепишите существующую статью в указанном редакционном тоне.'],
            'category' => 'content',
            'agentType' => 'content',
            'promptTemplate' => 'Rescrie articolul cu ID {articleId} în ton {tone}. Păstrează faptele, reformulează pentru claritate și impact.',
            'requiredFields' => ['articleId', 'tone'],
            'sortOrder' => 2,
        ],
        [
            'ro' => ['name' => 'Generează lead pentru articol', 'description' => 'Generează mai multe variante de lead (paragraf introductiv) pentru un articol.'],
            'en' => ['name' => 'Generate article lead', 'description' => 'Generate multiple lead (introductory paragraph) variants for an article.'],
            'ru' => ['name' => 'Сгенерировать лид для статьи', 'description' => 'Создание нескольких вариантов лида (вступительного абзаца) для статьи.'],
            'category' => 'content',
            'agentType' => 'content',
            'promptTemplate' => 'Generează 3 variante de lead (paragraf introductiv) pentru articolul cu ID {articleId}. Fiecare variantă să aibă un unghi diferit.',
            'requiredFields' => ['articleId'],
            'sortOrder' => 3,
        ],
        [
            'ro' => ['name' => 'Traduce articolul', 'description' => 'Traduce un articol în limbile specificate folosind terminologie jurnalistică. Powered by Gemini.'],
            'en' => ['name' => 'Translate article', 'description' => 'Translate an article into specified languages using journalistic terminology. Powered by Gemini.'],
            'ru' => ['name' => 'Перевести статью', 'description' => 'Перевод статьи на указанные языки с использованием журналистской терминологии. Powered by Gemini.'],
            'category' => 'translation',
            'agentType' => 'translation',
            'promptTemplate' => 'Traduce articolul cu ID {articleId} în limbile: {locales}. Foloseste terminologia jurnalistică standard.',
            'requiredFields' => ['articleId', 'locales'],
            'sortOrder' => 4,
        ],
        [
            'ro' => ['name' => 'Evaluează traducerea', 'description' => 'Evaluează calitatea traducerii unui articol: acuratețe, fluență, terminologie. Powered by Gemini.'],
            'en' => ['name' => 'Evaluate translation', 'description' => 'Evaluate translation quality of an article: accuracy, fluency, terminology. Powered by Gemini.'],
            'ru' => ['name' => 'Оценить перевод', 'description' => 'Оценка качества перевода статьи: точность, беглость, терминология. Powered by Gemini.'],
            'category' => 'translation',
            'agentType' => 'translation',
            'promptTemplate' => 'Evaluează calitatea traducerii articolului {articleId} pentru limba {locale}. Verifică: acuratețe, fluenta, terminologie, diacritice.',
            'requiredFields' => ['articleId', 'locale'],
            'sortOrder' => 5,
        ],
        [
            'ro' => ['name' => 'Briefing zilnic', 'description' => 'Generează un briefing zilnic pe un subiect din ultimele ore.'],
            'en' => ['name' => 'Daily briefing', 'description' => 'Generate a daily briefing on a topic from recent hours.'],
            'ru' => ['name' => 'Ежедневный брифинг', 'description' => 'Создание ежедневного брифинга по теме за последние часы.'],
            'category' => 'briefing',
            'agentType' => 'briefing',
            'promptTemplate' => 'Generează briefing-ul zilnic pentru subiectul {topic} din ultimele {hours} ore. Include: articole noi, tendințe, acțiuni recomandate.',
            'requiredFields' => ['topic', 'hours'],
            'sortOrder' => 6,
        ],
        [
            'ro' => ['name' => 'Rezumat săptămânal', 'description' => 'Creează rezumatul săptămânal al știrilor principale, grupate pe categorii.'],
            'en' => ['name' => 'Weekly summary', 'description' => 'Create a weekly summary of main news, grouped by categories.'],
            'ru' => ['name' => 'Еженедельный обзор', 'description' => 'Создание еженедельного обзора основных новостей, сгруппированных по категориям.'],
            'category' => 'briefing',
            'agentType' => 'briefing',
            'promptTemplate' => 'Creează rezumatul săptămânal al știrilor principale. Grupează pe categorii, evidențiază tendințele și sugerează subiecte de urmărit.',
            'requiredFields' => [],
            'sortOrder' => 7,
        ],
        // SEO Templates (Powered by Gemini)
        [
            'ro' => ['name' => 'Optimizează SEO pentru articol', 'description' => 'Generează metaTitle, metaDescription și keywords SEO optimizate. Powered by Gemini.'],
            'en' => ['name' => 'Optimize article SEO', 'description' => 'Generate optimized metaTitle, metaDescription and SEO keywords. Powered by Gemini.'],
            'ru' => ['name' => 'Оптимизировать SEO статьи', 'description' => 'Генерация оптимизированных metaTitle, metaDescription и ключевых слов SEO. Powered by Gemini.'],
            'category' => 'seo',
            'agentType' => 'seo',
            'promptTemplate' => 'Analizează articolul cu ID {articleId} și generează: 1) metaTitle optimizat (max 60 caractere), 2) metaDescription (max 155 caractere), 3) 5-7 keywords relevante, 4) sugestii de îmbunătățire a titlului pentru SEO.',
            'requiredFields' => ['articleId'],
            'sortOrder' => 8,
        ],
        [
            'ro' => ['name' => 'Generează meta tags multilingv', 'description' => 'Generează meta tags SEO adaptate cultural pentru mai multe limbi. Powered by Gemini.'],
            'en' => ['name' => 'Generate multilingual meta tags', 'description' => 'Generate culturally adapted SEO meta tags for multiple languages. Powered by Gemini.'],
            'ru' => ['name' => 'Создать мультиязычные мета-теги', 'description' => 'Генерация культурно адаптированных SEO мета-тегов для нескольких языков. Powered by Gemini.'],
            'category' => 'seo',
            'agentType' => 'seo',
            'promptTemplate' => 'Generează meta tags SEO pentru articolul {articleId} în limbile: {locales}. Adaptează keywords-urile cultural pentru fiecare limbă țintă.',
            'requiredFields' => ['articleId', 'locales'],
            'sortOrder' => 9,
        ],
        [
            'ro' => ['name' => 'Audit SEO conținut', 'description' => 'Audit complet SEO: densitate keywords, structură headings, readability. Powered by Gemini.'],
            'en' => ['name' => 'SEO content audit', 'description' => 'Complete SEO audit: keyword density, heading structure, readability. Powered by Gemini.'],
            'ru' => ['name' => 'SEO-аудит контента', 'description' => 'Полный SEO-аудит: плотность ключевых слов, структура заголовков, читабельность. Powered by Gemini.'],
            'category' => 'seo',
            'agentType' => 'seo',
            'promptTemplate' => 'Efectuează un audit SEO complet al articolului {articleId}: densitate keywords, structură headings, readability score, internal linking opportunities.',
            'requiredFields' => ['articleId'],
            'sortOrder' => 10,
        ],
    ];

    public static function getGroups(): array
    {
        return ['ai'];
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::TEMPLATES as $data) {
            $template = new AiPromptTemplate();

            // Set Romanian (default locale)
            $template->setName($data['ro']['name']);
            $template->setDescription($data['ro']['description']);
            $template->setCategory($data['category']);
            $template->setAgentType($data['agentType']);
            $template->setPromptTemplate($data['promptTemplate']);
            $template->setRequiredFields($data['requiredFields']);
            $template->setSortOrder($data['sortOrder']);
            $template->setTranslatableLocale('ro');

            $manager->persist($template);
            $manager->flush();

            // English translation
            $template->setName($data['en']['name']);
            $template->setDescription($data['en']['description']);
            $template->setTranslatableLocale('en');
            $manager->persist($template);
            $manager->flush();

            // Russian translation
            $template->setName($data['ru']['name']);
            $template->setDescription($data['ru']['description']);
            $template->setTranslatableLocale('ru');
            $manager->persist($template);
            $manager->flush();

            // Reset to default locale
            $manager->refresh($template);
            $template->setTranslatableLocale('ro');
        }

        echo '✅ Created ' . \count(self::TEMPLATES) . " AI prompt templates with translations (ro/en/ru)\n";
    }
}
