<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Dto\Editorial\VaultSyncResult;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Tag;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Service\Editorial\DbToVaultSyncService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Gedmo\Translatable\Entity\Translation;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DbToVaultSyncServiceTest extends TestCase
{
    private string $vaultPath;
    private EntityManagerInterface $em;
    private ArticleRepository $articleRepository;
    private DbToVaultSyncService $service;

    protected function setUp(): void
    {
        $this->vaultPath = sys_get_temp_dir() . '/vault_test_' . uniqid();
        mkdir($this->vaultPath, 0o755, true);

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->articleRepository = $this->createMock(ArticleRepository::class);

        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->method('findTranslations')->willReturn([
            'en' => ['title' => 'English Title', 'slug' => 'english-title', 'lead' => 'English lead'],
            'ru' => ['title' => 'Русский заголовок', 'slug' => 'russkij-zagolovok'],
        ]);

        $this->em->method('getRepository')
            ->with(Translation::class)
            ->willReturn($translationRepo);

        $this->service = new DbToVaultSyncService(
            $this->em,
            $this->articleRepository,
            new NullLogger(),
            $this->vaultPath,
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->vaultPath);
    }

    public function testSyncNewArticleCreatesMarkdownFile(): void
    {
        $article = $this->createArticle(1, 'Titlul articolului', 'titlul-articolului');

        $result = $this->service->syncArticleToVault($article);

        $this->assertSame(VaultSyncResult::ACTION_CREATED, $result->action);
        $this->assertTrue($result->isSuccess());
        $this->assertNotNull($result->vaultPath);
        $this->assertFileExists($this->vaultPath . '/' . $result->vaultPath);

        $content = file_get_contents($this->vaultPath . '/' . $result->vaultPath);
        $this->assertStringStartsWith('---', $content);
        $this->assertStringContainsString('Titlul articolului', $content);
    }

    public function testSyncExistingArticleUpdatesFile(): void
    {
        $article = $this->createArticle(2, 'Articol existent', 'articol-existent');

        // First sync creates
        $result1 = $this->service->syncArticleToVault($article);
        $this->assertSame(VaultSyncResult::ACTION_CREATED, $result1->action);

        // Second sync updates
        $result2 = $this->service->syncArticleToVault($article);
        $this->assertSame(VaultSyncResult::ACTION_UPDATED, $result2->action);
        $this->assertTrue($result2->isSuccess());
    }

    public function testFrontmatterContainsTranslations(): void
    {
        $article = $this->createArticle(3, 'Articol cu traduceri', 'articol-cu-traduceri');

        $frontmatter = $this->service->buildFrontmatter($article);

        $this->assertSame('Articol cu traduceri', $frontmatter['title']['ro']);
        $this->assertSame('English Title', $frontmatter['title']['en']);
        $this->assertSame('Русский заголовок', $frontmatter['title']['ru']);
        $this->assertSame('english-title', $frontmatter['slug']['en']);
        $this->assertSame('English lead', $frontmatter['description']['en']);
    }

    public function testArchiveMovesFileToArchived(): void
    {
        $article = $this->createArticle(4, 'Articol de arhivat', 'articol-de-arhivat');

        $this->service->syncArticleToVault($article);

        $createdAt = $article->getCreatedAt();
        $result = $this->service->archiveArticleFromVault(4, 'articol-de-arhivat', $createdAt);

        $this->assertSame(VaultSyncResult::ACTION_ARCHIVED, $result->action);
        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('_archived/', $result->vaultPath);
    }

    public function testVaultPathFollowsYearMonthStructure(): void
    {
        $article = $this->createArticle(5, 'Test cale', 'test-cale');

        $result = $this->service->syncArticleToVault($article);

        $this->assertMatchesRegularExpression(
            '#^articles/\d{4}/\d{2}/test-cale\.md$#',
            $result->vaultPath,
        );
    }

    public function testUtf8NfcDiacriticsPreserved(): void
    {
        $article = $this->createArticle(6, 'Societatea și țara noastră', 'societatea-si-tara-noastra');

        $result = $this->service->syncArticleToVault($article);
        $content = file_get_contents($this->vaultPath . '/' . $result->vaultPath);

        // Verify comma-below diacritics ș/ț are preserved
        $this->assertStringContainsString('Societatea și țara noastră', $content);
    }

    public function testFrontmatterContainsCategoryAndTags(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getSlug')->willReturn('politica');

        $tag1 = $this->createStub(Tag::class);
        $tag1->method('getSlug')->willReturn('moldova');
        $tag2 = $this->createStub(Tag::class);
        $tag2->method('getSlug')->willReturn('ue');

        $article = $this->createArticle(7, 'Cu categorie', 'cu-categorie', category: $category, tags: [$tag1, $tag2]);

        $frontmatter = $this->service->buildFrontmatter($article);

        $this->assertSame(['politica'], $frontmatter['categories']);
        $this->assertSame(['moldova', 'ue'], $frontmatter['tags']);
    }

    public function testFrontmatterMapsStatusCorrectly(): void
    {
        $article = $this->createArticle(8, 'Published', 'published', status: ArticleStatus::PUBLISHED);
        $frontmatter = $this->service->buildFrontmatter($article);
        $this->assertSame('published', $frontmatter['status']);

        $article2 = $this->createArticle(9, 'Draft', 'draft', status: ArticleStatus::NEW);
        $frontmatter2 = $this->service->buildFrontmatter($article2);
        $this->assertSame('draft', $frontmatter2['status']);
    }

    public function testSyncFailsWhenVaultPathEmpty(): void
    {
        $service = new DbToVaultSyncService(
            $this->em,
            $this->articleRepository,
            new NullLogger(),
            '',
        );

        $article = $this->createArticle(10, 'No vault', 'no-vault');
        $result = $service->syncArticleToVault($article);

        $this->assertSame(VaultSyncResult::ACTION_FAILED, $result->action);
        $this->assertFalse($result->isSuccess());
    }

    public function testMarkdownContentHasFrontmatterAndBody(): void
    {
        $article = $this->createArticle(11, 'Full content', 'full-content', content: '<p>Paragraf <strong>bold</strong> text.</p>');

        $content = $this->service->buildMarkdownContent($article);

        $this->assertStringStartsWith("---\n", $content);
        $this->assertStringContainsString("---\n\n# Full content\n\n", $content);
        $this->assertStringContainsString('bold', $content);
    }

    private function createArticle(
        int $id,
        string $title,
        string $slug,
        ?string $content = null,
        ArticleStatus $status = ArticleStatus::PUBLISHED,
        ?Category $category = null,
        array $tags = [],
    ): Article {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn($id);
        $article->method('getTitle')->willReturn($title);
        $article->method('getSlug')->willReturn($slug);
        $article->method('getLead')->willReturn('Lead text for ' . $title);
        $article->method('getContent')->willReturn($content ?? 'Content for ' . $title);
        $article->method('getStatus')->willReturn($status);
        $article->method('getBadge')->willReturn(null);
        $article->method('isFeatured')->willReturn(false);
        $article->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2026-04-01'));
        $article->method('getUpdatedAt')->willReturn(new \DateTimeImmutable('2026-04-02'));
        $article->method('getPublishedAt')->willReturn(new \DateTimeImmutable('2026-04-01'));
        $article->method('getMetaTitle')->willReturn(null);
        $article->method('getMetaDescription')->willReturn(null);
        $article->method('getCategory')->willReturn($category);
        $article->method('getAuthors')->willReturn(new ArrayCollection());
        $article->method('getTags')->willReturn(new ArrayCollection($tags));
        $article->method('getSourceEmail')->willReturn(null);
        $article->method('getContentHash')->willReturn(null);
        $article->method('getWebcode')->willReturn(null);

        return $article;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($path);
    }
}
