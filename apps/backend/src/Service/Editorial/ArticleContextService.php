<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds structured context (Markdown/JSON) from Article entities — zero disk I/O.
 * Reusable by CLI commands, AI agents, and GeneratedContent (Sprint 23).
 */
class ArticleContextService
{
    private ?HtmlConverter $htmlConverter = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Build complete Markdown document with YAML frontmatter for a single article.
     */
    public function buildMarkdownForArticle(Article $article, string $locale = 'ro'): string
    {
        if ($locale !== 'ro') {
            $article->setTranslatableLocale($locale);
            $this->em->refresh($article);
        }

        $frontmatter = $this->buildFrontmatter($article);
        $yamlContent = Yaml::dump($frontmatter, 4, 2, Yaml::DUMP_NULL_AS_TILDE);

        $body = $this->convertToMarkdown($article->getContent() ?? '');

        return "---\n{$yamlContent}---\n\n# {$article->getTitle()}\n\n{$body}\n";
    }

    /**
     * Build structured JSON array for a single article.
     *
     * @return array<string, mixed>
     */
    public function buildJsonForArticle(Article $article, string $locale = 'ro'): array
    {
        if ($locale !== 'ro') {
            $article->setTranslatableLocale($locale);
            $this->em->refresh($article);
        }

        $frontmatter = $this->buildFrontmatter($article);

        return [
            ...$frontmatter,
            'content_markdown' => $this->convertToMarkdown($article->getContent() ?? ''),
            'content_html' => $article->getContent() ?? '',
            'lead' => $article->getLead(),
        ];
    }

    /**
     * Build extended context bundle including topics, tags, and related articles.
     * Designed for AI agent consumption with maximum context.
     */
    public function buildContextBundle(Article $article): string
    {
        $markdown = $this->buildMarkdownForArticle($article, 'ro');

        // Append topics section
        $topics = $article->getTopics()->toArray();
        if ($topics !== []) {
            $markdown .= "\n## Topics\n\n";
            foreach ($topics as $topic) {
                $markdown .= "- **{$topic->getTitle()}** (`{$topic->getSlug()}`)\n";
            }
        }

        // Append tags section
        $tags = $article->getTags()->toArray();
        if ($tags !== []) {
            $markdown .= "\n## Tags\n\n";
            foreach ($tags as $tag) {
                $markdown .= "- {$tag->getName()} (`{$tag->getSlug()}`)\n";
            }
        }

        // Append translation status
        $translations = $this->getTranslations($article);
        $markdown .= "\n## Translation Status\n\n";
        $markdown .= "- **RO**: complete (original)\n";
        $markdown .= '- **EN**: ' . (isset($translations['en']['title']) ? 'complete' : 'pending') . "\n";
        $markdown .= '- **RU**: ' . (isset($translations['ru']['title']) ? 'complete' : 'pending') . "\n";

        return $markdown;
    }

    /**
     * Build frontmatter array from Article entity with trilingual translations.
     *
     * @return array<string, mixed>
     */
    public function buildFrontmatter(Article $article): array
    {
        $translations = $this->getTranslations($article);

        $date = $article->getCreatedAt() ?? new \DateTimeImmutable();
        $slug = $article->getSlug() ?: 'untitled-' . $article->getId();
        $id = sprintf('art-%s-%s', $date->format('Y-m-d'), $slug);
        if (mb_strlen($id) > 128) {
            $id = mb_substr($id, 0, 128);
        }

        return [
            'id' => $id,
            'article_id' => $article->getId(),
            'type' => $this->mapArticleType($article),
            'language' => 'ro',

            'title' => [
                'ro' => $article->getTitle(),
                'en' => $translations['en']['title'] ?? null,
                'ru' => $translations['ru']['title'] ?? null,
            ],
            'description' => [
                'ro' => $article->getLead() ?? $this->truncate($article->getContent(), 300),
                'en' => $translations['en']['lead'] ?? $translations['en']['description'] ?? null,
                'ru' => $translations['ru']['lead'] ?? $translations['ru']['description'] ?? null,
            ],
            'slug' => [
                'ro' => $article->getSlug(),
                'en' => $translations['en']['slug'] ?? null,
                'ru' => $translations['ru']['slug'] ?? null,
            ],

            'seo' => [
                'meta_title' => [
                    'ro' => $article->getMetaTitle(),
                    'en' => $translations['en']['metaTitle'] ?? null,
                    'ru' => $translations['ru']['metaTitle'] ?? null,
                ],
                'meta_description' => [
                    'ro' => $article->getMetaDescription(),
                    'en' => $translations['en']['metaDescription'] ?? null,
                    'ru' => $translations['ru']['metaDescription'] ?? null,
                ],
                'og_type' => 'article',
                'twitter_card' => 'summary_large_image',
            ],

            'author' => $this->mapAuthors($article),
            'date_created' => $article->getCreatedAt()?->format('c'),
            'date_published' => $article->getPublishedAt()?->format('c'),
            'date_modified' => $article->getUpdatedAt()?->format('c'),
            'status' => $this->mapStatus($article),
            'badge' => $article->getBadge()?->value,
            'is_featured' => $article->isFeatured(),

            'categories' => $article->getCategory() ? [$article->getCategory()->getSlug()] : [],
            'tags' => array_map(fn ($t) => $t->getSlug(), $article->getTags()->toArray()),
            'topics' => array_map(fn ($t) => [
                'id' => $t->getId(),
                'title' => $t->getTitle(),
                'slug' => $t->getSlug(),
            ], $article->getTopics()->toArray()),

            'source' => [
                'name' => 'Deschide News',
                'source_id' => $article->getSourceEmail(),
                'content_hash' => $article->getContentHash(),
                'webcode' => $article->getWebcode(),
            ],

            'ai' => [
                'translation_status' => [
                    'ro' => 'complete',
                    'en' => isset($translations['en']['title']) ? 'complete' : 'pending',
                    'ru' => isset($translations['ru']['title']) ? 'complete' : 'pending',
                ],
                'summary' => $article->getInternalSummary(),
                'auto_generated' => false,
                'reviewed' => true,
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getTranslations(Article $article): array
    {
        $translationRepo = $this->em->getRepository(Translation::class);

        return $translationRepo->findTranslations($article);
    }

    private function mapArticleType(Article $article): string
    {
        $badge = $article->getBadge();
        if ($badge !== null) {
            return match ($badge->value) {
                'analysis' => 'analysis',
                'opinion' => 'opinion',
                'video' => 'video',
                'photo_gallery' => 'photo-gallery',
                default => 'news',
            };
        }

        return 'news';
    }

    private function mapStatus(Article $article): string
    {
        return match ($article->getStatus()) {
            ArticleStatus::NEW => 'draft',
            ArticleStatus::SUBMITTED => 'review',
            ArticleStatus::PUBLISHED, ArticleStatus::PUBLISHED_FULL => 'published',
            ArticleStatus::ARCHIVED => 'archived',
        };
    }

    /**
     * @return list<string>
     */
    private function mapAuthors(Article $article): array
    {
        $authors = $article->getAuthors();
        if ($authors->isEmpty()) {
            return [];
        }

        return array_map(fn ($a) => $a->getSlug(), $authors->toArray());
    }

    private function truncate(?string $text, int $length): ?string
    {
        if ($text === null || $text === '') {
            return null;
        }

        $plain = strip_tags($text);
        if (mb_strlen($plain) <= $length) {
            return $plain;
        }

        return mb_substr($plain, 0, $length) . '…';
    }

    private function convertToMarkdown(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (strip_tags($html) === $html) {
            return $html;
        }

        return $this->getHtmlConverter()->convert($html);
    }

    private function getHtmlConverter(): HtmlConverter
    {
        if ($this->htmlConverter === null) {
            $this->htmlConverter = new HtmlConverter([
                'strip_tags' => false,
                'hard_break' => true,
                'remove_nodes' => 'script style',
            ]);
        }

        return $this->htmlConverter;
    }
}
