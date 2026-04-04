<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Tag;
use App\Entity\Topic;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Service\Editorial\ArticleContextService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Gedmo\Translatable\Entity\Translation;
use PHPUnit\Framework\TestCase;

class ArticleContextServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ArticleContextService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->method('findTranslations')->willReturn([
            'en' => ['title' => 'English Title', 'slug' => 'english-title', 'lead' => 'English lead'],
            'ru' => ['title' => 'Русский заголовок', 'slug' => 'russkij-zagolovok'],
        ]);

        $this->em->method('getRepository')
            ->with(Translation::class)
            ->willReturn($translationRepo);

        $this->service = new ArticleContextService($this->em);
    }

    public function testBuildMarkdownForArticle_returnsValidFrontmatter(): void
    {
        $article = $this->createArticle(1, 'Titlu test', 'titlu-test');

        $markdown = $this->service->buildMarkdownForArticle($article);

        $this->assertStringStartsWith('---', $markdown);
        $this->assertStringContainsString("---\n\n# Titlu test", $markdown);
        $this->assertStringContainsString('article_id: 1', $markdown);
        $this->assertStringContainsString('type: news', $markdown);

        // Verify YAML frontmatter has opening and closing delimiters
        $parts = explode("---\n", $markdown);
        $this->assertGreaterThanOrEqual(3, \count($parts), 'Should have opening and closing --- delimiters');
    }

    public function testBuildMarkdownForArticle_defaultLocaleRo(): void
    {
        $article = $this->createArticle(2, 'Articol Românesc', 'articol-romanesc');

        $markdown = $this->service->buildMarkdownForArticle($article, 'ro');

        $this->assertStringContainsString('# Articol Românesc', $markdown);
        $this->assertStringContainsString('language: ro', $markdown);
    }

    public function testBuildMarkdownForArticle_switchesLocale(): void
    {
        $article = $this->createArticle(3, 'Titlu EN', 'titlu-en');

        // When locale is not 'ro', the service calls setTranslatableLocale + refresh
        $this->em->expects($this->once())
            ->method('refresh')
            ->with($article);

        $this->service->buildMarkdownForArticle($article, 'en');
    }

    public function testBuildMarkdownForArticle_handlesHtmlContent(): void
    {
        $article = $this->createArticle(4, 'HTML Article', 'html-article', '<p>Paragraph <strong>bold</strong></p>');

        $markdown = $this->service->buildMarkdownForArticle($article);

        // Should contain converted markdown, not raw HTML
        $this->assertStringContainsString('**bold**', $markdown);
    }

    public function testBuildMarkdownForArticle_handlesPlainTextContent(): void
    {
        $article = $this->createArticle(5, 'Plain Article', 'plain-article', 'Just plain text content.');

        $markdown = $this->service->buildMarkdownForArticle($article);

        $this->assertStringContainsString('Just plain text content.', $markdown);
    }

    public function testBuildMarkdownForArticle_handlesEmptyContent(): void
    {
        $article = $this->createArticle(6, 'Empty Content', 'empty-content', '');

        $markdown = $this->service->buildMarkdownForArticle($article);

        $this->assertStringStartsWith('---', $markdown);
        $this->assertStringContainsString('# Empty Content', $markdown);
    }

    public function testBuildJsonForArticle_returnsExpectedKeys(): void
    {
        $article = $this->createArticle(10, 'JSON Test', 'json-test');

        $json = $this->service->buildJsonForArticle($article);

        // Frontmatter keys
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('article_id', $json);
        $this->assertArrayHasKey('type', $json);
        $this->assertArrayHasKey('language', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertArrayHasKey('description', $json);
        $this->assertArrayHasKey('slug', $json);
        $this->assertArrayHasKey('seo', $json);
        $this->assertArrayHasKey('author', $json);
        $this->assertArrayHasKey('date_created', $json);
        $this->assertArrayHasKey('date_published', $json);
        $this->assertArrayHasKey('date_modified', $json);
        $this->assertArrayHasKey('status', $json);
        $this->assertArrayHasKey('categories', $json);
        $this->assertArrayHasKey('tags', $json);
        $this->assertArrayHasKey('topics', $json);
        $this->assertArrayHasKey('source', $json);
        $this->assertArrayHasKey('ai', $json);

        // JSON-specific keys
        $this->assertArrayHasKey('content_markdown', $json);
        $this->assertArrayHasKey('content_html', $json);
        $this->assertArrayHasKey('lead', $json);

        // Values
        $this->assertSame(10, $json['article_id']);
        $this->assertSame('news', $json['type']);
        $this->assertSame('ro', $json['language']);
    }

    public function testBuildJsonForArticle_includesTrilingualTitles(): void
    {
        $article = $this->createArticle(11, 'Titlu RO', 'titlu-ro');

        $json = $this->service->buildJsonForArticle($article);

        $this->assertSame('Titlu RO', $json['title']['ro']);
        $this->assertSame('English Title', $json['title']['en']);
        $this->assertSame('Русский заголовок', $json['title']['ru']);
    }

    public function testBuildContextBundle_includesTopicsAndTags(): void
    {
        // Create article with tags and topics directly (cannot override mock methods)
        $tag1 = $this->createMock(Tag::class);
        $tag1->method('getName')->willReturn('Politică');
        $tag1->method('getSlug')->willReturn('politica');
        $tag2 = $this->createMock(Tag::class);
        $tag2->method('getName')->willReturn('Economie');
        $tag2->method('getSlug')->willReturn('economie');

        $topic = $this->createMock(Topic::class);
        $topic->method('getTitle')->willReturn('Integrare UE');
        $topic->method('getSlug')->willReturn('integrare-ue');
        $topic->method('getId')->willReturn(1);

        $article = $this->createArticleWithCollections(
            20,
            'Bundle Test',
            'bundle-test',
            new ArrayCollection([$tag1, $tag2]),
            new ArrayCollection([$topic]),
        );

        $bundle = $this->service->buildContextBundle($article);

        $this->assertStringContainsString('## Topics', $bundle);
        $this->assertStringContainsString('**Integrare UE**', $bundle);
        $this->assertStringContainsString('`integrare-ue`', $bundle);

        $this->assertStringContainsString('## Tags', $bundle);
        $this->assertStringContainsString('Politică', $bundle);
        $this->assertStringContainsString('`economie`', $bundle);

        $this->assertStringContainsString('## Translation Status', $bundle);
        $this->assertStringContainsString('**EN**: complete', $bundle);
    }

    public function testBuildContextBundle_handlesNoTopicsOrTags(): void
    {
        $article = $this->createArticle(21, 'Empty Bundle', 'empty-bundle');

        $bundle = $this->service->buildContextBundle($article);

        // Should still have translation status
        $this->assertStringContainsString('## Translation Status', $bundle);
        // Should not have topics/tags sections when empty
        $this->assertStringNotContainsString('## Topics', $bundle);
        $this->assertStringNotContainsString('## Tags', $bundle);
    }

    public function testBuildFrontmatter_mapsArticleBadge(): void
    {
        $article = $this->createArticleWithCollections(
            30,
            'Breaking Article',
            'breaking-article',
            new ArrayCollection(),
            new ArrayCollection(),
            ArticleBadge::BREAKING,
        );

        $frontmatter = $this->service->buildFrontmatter($article);

        $this->assertSame('breaking', $frontmatter['badge']);
    }

    public function testBuildFrontmatter_mapsCategorySlug(): void
    {
        $article = $this->createArticle(31, 'Categorized', 'categorized');

        $frontmatter = $this->service->buildFrontmatter($article);

        $this->assertSame(['politica'], $frontmatter['categories']);
    }

    public function testBuildFrontmatter_mapsAuthors(): void
    {
        $article = $this->createArticle(32, 'Authored', 'authored');

        $frontmatter = $this->service->buildFrontmatter($article);

        $this->assertSame(['ion-popescu'], $frontmatter['author']);
    }

    public function testBuildFrontmatter_truncatesLongId(): void
    {
        $longSlug = str_repeat('a', 200);
        $article = $this->createArticle(33, 'Long slug', $longSlug);

        $frontmatter = $this->service->buildFrontmatter($article);

        $this->assertLessThanOrEqual(128, mb_strlen($frontmatter['id']));
    }

    public function testBuildFrontmatter_translationStatusReflectsAvailability(): void
    {
        $article = $this->createArticle(34, 'Translated', 'translated');

        $frontmatter = $this->service->buildFrontmatter($article);

        $this->assertSame('complete', $frontmatter['ai']['translation_status']['ro']);
        $this->assertSame('complete', $frontmatter['ai']['translation_status']['en']);
        $this->assertSame('complete', $frontmatter['ai']['translation_status']['ru']);
    }

    /**
     * @return Article&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createArticle(int $id, string $title, string $slug, string $content = '<p>Conținutul articolului</p>'): Article
    {
        return $this->createArticleWithCollections($id, $title, $slug, new ArrayCollection(), new ArrayCollection(), null, $content);
    }

    /**
     * @return Article&\PHPUnit\Framework\MockObject\MockObject
     */
    private function createArticleWithCollections(
        int $id,
        string $title,
        string $slug,
        ArrayCollection $tags,
        ArrayCollection $topics,
        ?ArticleBadge $badge = null,
        string $content = '<p>Conținutul articolului</p>',
    ): Article {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn($id);
        $article->method('getTitle')->willReturn($title);
        $article->method('getSlug')->willReturn($slug);
        $article->method('getContent')->willReturn($content);
        $article->method('getLead')->willReturn('Lead text');
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $article->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2025-01-15'));
        $article->method('getPublishedAt')->willReturn(new \DateTimeImmutable('2025-01-15'));
        $article->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2025-01-16'));
        $article->method('getBadge')->willReturn($badge);
        $article->method('isFeatured')->willReturn(false);
        $article->method('getMetaTitle')->willReturn(null);
        $article->method('getMetaDescription')->willReturn(null);
        $article->method('getSourceEmail')->willReturn(null);
        $article->method('getContentHash')->willReturn('abc123');
        $article->method('getWebcode')->willReturn(null);
        $article->method('getInternalSummary')->willReturn(null);

        $category = $this->createMock(Category::class);
        $category->method('getSlug')->willReturn('politica');
        $category->method('getTitle')->willReturn('Politica');
        $article->method('getCategory')->willReturn($category);

        $author = $this->createMock(Author::class);
        $author->method('getSlug')->willReturn('ion-popescu');
        $article->method('getAuthors')->willReturn(new ArrayCollection([$author]));

        $article->method('getTags')->willReturn($tags);
        $article->method('getTopics')->willReturn($topics);

        return $article;
    }
}
