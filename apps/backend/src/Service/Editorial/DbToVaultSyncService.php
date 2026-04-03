<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Dto\Editorial\BatchSyncResult;
use App\Dto\Editorial\VaultSyncResult;
use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use League\HTMLToMarkdown\HtmlConverter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Yaml;

class DbToVaultSyncService
{
    private ?HtmlConverter $htmlConverter = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ArticleRepository $articleRepository,
        private readonly LoggerInterface $logger,
        private readonly string $vaultPath,
    ) {}

    /**
     * Sincronizează un articol din DB în vault (creare sau update .md).
     */
    public function syncArticleToVault(Article $article): VaultSyncResult
    {
        if ($this->vaultPath === '') {
            return new VaultSyncResult(
                articleId: $article->getId(),
                action: VaultSyncResult::ACTION_FAILED,
                error: 'Vault path not configured',
            );
        }

        try {
            $relativePath = $this->getVaultRelativePath($article);
            $fullPath = $this->vaultPath . '/' . $relativePath;
            $exists = is_file($fullPath);

            $content = $this->buildMarkdownContent($article);

            $dir = \dirname($fullPath);
            if (!is_dir($dir) && !mkdir($dir, 0o755, true)) {
                return new VaultSyncResult(
                    articleId: $article->getId(),
                    action: VaultSyncResult::ACTION_FAILED,
                    error: "Cannot create directory: {$dir}",
                );
            }

            file_put_contents($fullPath, $content);

            $action = $exists ? VaultSyncResult::ACTION_UPDATED : VaultSyncResult::ACTION_CREATED;

            $this->logger->info('DbToVaultSync: article synced', [
                'articleId' => $article->getId(),
                'path' => $relativePath,
                'action' => $action,
            ]);

            return new VaultSyncResult(
                articleId: $article->getId(),
                action: $action,
                vaultPath: $relativePath,
            );
        } catch (\Throwable $e) {
            $this->logger->error('DbToVaultSync: sync failed', [
                'articleId' => $article->getId(),
                'error' => $e->getMessage(),
            ]);

            return new VaultSyncResult(
                articleId: $article->getId(),
                action: VaultSyncResult::ACTION_FAILED,
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Arhivează fișierul .md (mutare în _archived/, nu ștergere fizică).
     */
    public function archiveArticleFromVault(int $articleId, string $slug, ?\DateTimeImmutable $createdAt = null): VaultSyncResult
    {
        if ($this->vaultPath === '') {
            return new VaultSyncResult(
                articleId: $articleId,
                action: VaultSyncResult::ACTION_FAILED,
                error: 'Vault path not configured',
            );
        }

        $date = $createdAt ?? new \DateTimeImmutable();
        $relativePath = sprintf('articles/%s/%s/%s.md', $date->format('Y'), $date->format('m'), $slug);
        $fullPath = $this->vaultPath . '/' . $relativePath;

        if (!is_file($fullPath)) {
            // Try to find the file by scanning year/month directories
            $found = $this->findArticleFile($slug);
            if ($found === null) {
                $this->logger->warning('DbToVaultSync: file not found for archival', [
                    'articleId' => $articleId,
                    'slug' => $slug,
                ]);

                return new VaultSyncResult(
                    articleId: $articleId,
                    action: VaultSyncResult::ACTION_SKIPPED,
                    error: "File not found: {$relativePath}",
                );
            }
            $fullPath = $found;
            $relativePath = str_replace($this->vaultPath . '/', '', $found);
        }

        try {
            $archivePath = str_replace('articles/', '_archived/', $relativePath);
            $archivePath = preg_replace('/\.md$/', '-' . time() . '.md', $archivePath);
            $archiveFullPath = $this->vaultPath . '/' . $archivePath;

            $archiveDir = \dirname($archiveFullPath);
            if (!is_dir($archiveDir) && !mkdir($archiveDir, 0o755, true)) {
                return new VaultSyncResult(
                    articleId: $articleId,
                    action: VaultSyncResult::ACTION_FAILED,
                    error: "Cannot create archive directory: {$archiveDir}",
                );
            }

            rename($fullPath, $archiveFullPath);

            $this->logger->info('DbToVaultSync: article archived', [
                'articleId' => $articleId,
                'from' => $relativePath,
                'to' => $archivePath,
            ]);

            return new VaultSyncResult(
                articleId: $articleId,
                action: VaultSyncResult::ACTION_ARCHIVED,
                vaultPath: $archivePath,
            );
        } catch (\Throwable $e) {
            $this->logger->error('DbToVaultSync: archive failed', [
                'articleId' => $articleId,
                'error' => $e->getMessage(),
            ]);

            return new VaultSyncResult(
                articleId: $articleId,
                action: VaultSyncResult::ACTION_FAILED,
                error: $e->getMessage(),
            );
        }
    }

    /**
     * Sync batch — toate articolele publicate.
     */
    public function syncAllToVault(int $limit = 100, int $offset = 0, ?\DateTimeImmutable $since = null): BatchSyncResult
    {
        $qb = $this->articleRepository->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($since !== null) {
            $qb->andWhere('a.updatedAt >= :since')
                ->setParameter('since', $since);
        }

        $articles = $qb->getQuery()->getResult();

        $results = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($articles as $article) {
            $result = $this->syncArticleToVault($article);
            $results[] = $result;

            match ($result->action) {
                VaultSyncResult::ACTION_CREATED => $created++,
                VaultSyncResult::ACTION_UPDATED => $updated++,
                VaultSyncResult::ACTION_SKIPPED => $skipped++,
                VaultSyncResult::ACTION_FAILED => $failed++,
                default => null,
            };
        }

        return new BatchSyncResult(
            results: $results,
            created: $created,
            updated: $updated,
            skipped: $skipped,
            failed: $failed,
        );
    }

    /**
     * Build complete Markdown document with YAML frontmatter.
     */
    public function buildMarkdownContent(Article $article): string
    {
        $frontmatter = $this->buildFrontmatter($article);
        $yamlContent = Yaml::dump($frontmatter, 4, 2, Yaml::DUMP_NULL_AS_TILDE);

        $body = $this->convertToMarkdown($article->getContent() ?? '');

        return "---\n{$yamlContent}---\n\n# {$article->getTitle()}\n\n{$body}\n";
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
            ArticleStatus::PUBLISHED => 'published',
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

        // If content is already plain text / markdown (no HTML tags), return as-is
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

    private function getVaultRelativePath(Article $article): string
    {
        $date = $article->getCreatedAt() ?? new \DateTimeImmutable();
        $slug = $article->getSlug() ?: 'untitled-' . $article->getId();

        return sprintf('articles/%s/%s/%s.md', $date->format('Y'), $date->format('m'), $slug);
    }

    private function findArticleFile(string $slug): ?string
    {
        $articlesDir = $this->vaultPath . '/articles';
        if (!is_dir($articlesDir)) {
            return null;
        }

        $filename = $slug . '.md';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($articlesDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }

        return null;
    }
}
