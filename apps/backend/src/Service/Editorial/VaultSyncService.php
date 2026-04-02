<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Tag;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Psr\Log\LoggerInterface;

class VaultSyncService
{
    private const LOCALES = ['ro', 'en', 'ru'];

    private const STATUS_MAP = [
        'draft' => ArticleStatus::NEW,
        'review' => ArticleStatus::SUBMITTED,
        'scheduled' => ArticleStatus::NEW,
        'published' => ArticleStatus::PUBLISHED,
        'archived' => ArticleStatus::ARCHIVED,
    ];

    public function __construct(
        private readonly MarkdownParser $parser,
        private readonly FrontmatterValidator $validator,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function syncFromContent(string $markdownContent): VaultSyncResult
    {
        // Parse markdown
        $parseResult = $this->parser->parse($markdownContent);
        if ($parseResult->frontmatter === []) {
            return new VaultSyncResult(VaultSyncResult::STATUS_PARSE_ERROR, errors: ['No frontmatter found']);
        }

        // Validate frontmatter
        $validation = $this->validator->validate($parseResult->frontmatter);
        if (!$validation->isValid) {
            return new VaultSyncResult(VaultSyncResult::STATUS_VALIDATION_ERROR, errors: $validation->errors);
        }

        $fm = $parseResult->frontmatter;
        $vaultId = $fm['id'];

        // Check if article already exists (by sourceEmail field storing the vault ID)
        $article = $this->em->getRepository(Article::class)->findOneBy(['sourceEmail' => $vaultId]);
        $isNew = $article === null;

        if ($isNew) {
            $article = new Article();
            $article->setSourceEmail($vaultId);
        }

        // Map status
        $statusKey = $fm['status'] ?? 'draft';
        $article->setStatus(self::STATUS_MAP[$statusKey] ?? ArticleStatus::NEW);

        // Map category (first one found)
        $this->mapCategory($article, $fm['categories'] ?? []);

        // Map authors
        $this->mapAuthors($article, $fm['author'] ?? null);

        // Map tags
        $this->mapTags($article, $fm['tags'] ?? []);

        // Map dates
        if (isset($fm['date_published']) && $fm['date_published'] !== null) {
            $article->setPublishedAt(new \DateTimeImmutable($fm['date_published']));
        }

        // Map SEO fields for default locale (ro)
        $this->mapSeoFields($article, $fm);

        // Set translatable fields for default locale (ro)
        $article->setTranslatableLocale('ro');
        $article->setTitle($fm['title']['ro'] ?? '');
        $article->setSlug($fm['slug']['ro'] ?? '');
        $article->setLead($fm['description']['ro'] ?? null);
        $article->setContent($parseResult->bodyHtml ?: null);
        $article->setMetaTitle(mb_substr($fm['title']['ro'] ?? '', 0, 60) ?: null);
        $article->setMetaDescription(mb_substr($fm['description']['ro'] ?? '', 0, 160) ?: null);

        $this->em->persist($article);
        $this->em->flush();

        // Now handle non-default locale translations (en, ru) via Gedmo Translation repository
        /** @var \Gedmo\Translatable\Entity\Repository\TranslationRepository $translationRepo */
        $translationRepo = $this->em->getRepository(Translation::class);

        foreach (['en', 'ru'] as $locale) {
            $title = $fm['title'][$locale] ?? null;
            $description = $fm['description'][$locale] ?? null;
            $slug = $fm['slug'][$locale] ?? null;

            if ($title !== null && $title !== '') {
                $translationRepo->translate($article, 'title', $locale, $title);
            }
            if ($description !== null && $description !== '') {
                $translationRepo->translate($article, 'lead', $locale, $description);
            }
            if ($slug !== null && $slug !== '') {
                $translationRepo->translate($article, 'slug', $locale, $slug);
            }

            // SEO keywords as metaDescription for non-default locales
            $keywords = $fm['seo']['keywords'][$locale] ?? null;
            if (is_array($keywords) && $keywords !== []) {
                $translationRepo->translate($article, 'metaDescription', $locale, mb_substr(implode(', ', $keywords), 0, 160));
            }

            // MetaTitle for non-default locales
            if ($title !== null && $title !== '') {
                $translationRepo->translate($article, 'metaTitle', $locale, mb_substr($title, 0, 60));
            }
        }

        $this->em->flush();

        $this->logger->info('Vault sync: article {status}', [
            'status' => $isNew ? 'created' : 'updated',
            'vault_id' => $vaultId,
            'article_id' => $article->getId(),
        ]);

        return new VaultSyncResult(
            $isNew ? VaultSyncResult::STATUS_CREATED : VaultSyncResult::STATUS_UPDATED,
            $article->getId(),
        );
    }

    public function syncFromFile(string $filePath): VaultSyncResult
    {
        if (!file_exists($filePath)) {
            return new VaultSyncResult(VaultSyncResult::STATUS_PARSE_ERROR, errors: ['File not found: ' . $filePath]);
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return new VaultSyncResult(VaultSyncResult::STATUS_PARSE_ERROR, errors: ['Cannot read file: ' . $filePath]);
        }

        return $this->syncFromContent($content);
    }

    /**
     * @param list<string> $categories
     */
    private function mapCategory(Article $article, array $categories): void
    {
        if ($categories === []) {
            return;
        }

        $categoryRepo = $this->em->getRepository(Category::class);

        // Try to find the first matching category by slug or title
        foreach ($categories as $categoryLabel) {
            $slug = $this->slugify($categoryLabel);
            $category = $categoryRepo->findOneBy(['slug' => $slug]);
            if ($category === null) {
                $category = $categoryRepo->findOneBy(['title' => $categoryLabel]);
            }
            if ($category !== null) {
                $article->setCategory($category);
                return;
            }
        }
    }

    private function mapAuthors(Article $article, ?string $authorSlug): void
    {
        if ($authorSlug === null || $authorSlug === '') {
            return;
        }

        $author = $this->em->getRepository(Author::class)->findOneBy(['slug' => $authorSlug]);
        if ($author !== null) {
            // Clear existing authors and add the one from vault
            foreach ($article->getAuthors() as $existingAuthor) {
                $article->removeAuthor($existingAuthor);
            }
            $article->addAuthor($author);
        }
    }

    /**
     * @param list<string> $tagSlugs
     */
    private function mapTags(Article $article, array $tagSlugs): void
    {
        if ($tagSlugs === []) {
            return;
        }

        // Clear existing tags
        foreach ($article->getTags() as $existingTag) {
            $article->removeTag($existingTag);
        }

        $tagRepo = $this->em->getRepository(Tag::class);
        foreach ($tagSlugs as $tagSlug) {
            $tag = $tagRepo->findOneBy(['slug' => $tagSlug]);
            if ($tag !== null) {
                $article->addTag($tag);
            }
        }
    }

    /**
     * @param array<string, mixed> $fm
     */
    private function mapSeoFields(Article $article, array $fm): void
    {
        $keywords = $fm['seo']['keywords']['ro'] ?? null;
        if (is_array($keywords) && $keywords !== []) {
            $article->setMetaDescription(mb_substr(implode(', ', $keywords), 0, 160));
        }
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

        return preg_replace('/-+/', '-', trim($text, '-'));
    }
}
