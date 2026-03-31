<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\ExternalArticleMapping;
use App\Entity\Image;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use App\Enum\CategoryStatus;
use App\Message\TranslateArticleMessage;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Repository\ExternalArticleMappingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Imports articles from deschide.md via Supabase REST API (paginated).
 *
 *  Source (Supabase)       → Symfony Field                    | Transform
 *  ──────────────────────────────────────────────────────────────────────
 *  id                      → ExternalArticleMapping.externalId| deduplication key
 *  title                   → Article.title                    | strip_tags, trim
 *  lead_text               → Article.lead                     | strip_tags, truncate 500
 *  content_text            → Article.content                  | full HTML body
 *  category                → Category (via CATEGORY_MAP)      | findOrCreate
 *  author                  → Author (findOrCreate)            | slug → full name
 *  published_at            → Article.publishedAt              | DateTimeImmutable
 *  main_image              → Image (download + attach)        | download to uploads/images/rss/
 *  is_breaking_news        → Article.badge = BREAKING         | priority: breaking > alert > flash
 *  is_news_alert           → Article.badge = ALERT            |
 *  is_flash_news           → Article.badge = FLASH            |
 *  is_featured             → Article.isFeatured               |
 *  title_ru                → Gedmo translation (ru)           | if non-empty
 *  lead_text_ru            → Gedmo translation (ru)           | if non-empty
 *  content_text_ru         → Gedmo translation (ru)           | if non-empty
 */
final class RssFeedImporter
{
    private const SOURCE_NAME = 'supabase_deschide_md';
    private const SUPABASE_URL = 'https://krjjgzewhghcdzckcjmb.supabase.co';
    private const SUPABASE_ANON_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtyampnemV3aGdoY2R6Y2tjam1iIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjIxNzIwNjMsImV4cCI6MjA3Nzc0ODA2M30.RKs9-zwJCKXGu2Jc45KEUw-oUS9yjZfPbMGKCNzaHAE';
    private const BATCH_SIZE = 50;
    private const PAGE_SIZE = 100;
    private const IMAGE_DIR = 'images/rss';

    private const SUPABASE_SELECT = 'id,title,slug,lead_text,content_text,category,author,published_at,main_image,is_breaking_news,is_news_alert,is_flash_news,is_featured,draft,archived,title_ru,lead_text_ru,content_text_ru';

    /**
     * Maps Supabase category names (lowercase) → existing DB category titles.
     */
    private const CATEGORY_MAP = [
        'social'       => 'Societate',
        'economic'     => 'Economie',
        'politic'      => 'Politică',
        'alegeri'      => 'Politică',
        'opinii'       => 'Opinii',
        'editorial'    => 'Editoriale',
        'externe'      => 'Externe',
        'cultura'      => 'Cultură',
        'advertorial'  => 'Advertorial',
        'transnistria' => 'Politică',
        'romania'      => 'România',
        'anti-fake'    => 'Anti-Fake',
        'sport'        => 'Sport',
        'diaspora'     => 'Diaspora',
        'sanatate'     => 'Sănătate',
        'educatie'     => 'Educație',
        'justitie'     => 'Justiție',
        'mediu'        => 'Mediu',
        'tehnologie'   => 'Tehnologie',
        'stiinta'      => 'Știință',
    ];

    /** @var array<string, Category> */
    private array $categoryCache = [];

    /** @var array<string, Author> */
    private array $authorCache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private EntityManagerInterface $em,
        private readonly ManagerRegistry $managerRegistry,
        private readonly ExternalArticleMappingRepository $mappingRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly AuthorRepository $authorRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly SluggerInterface $slugger,
        private readonly LoggerInterface $logger,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{total: int, imported: int, skipped: int, errors: int, details: list<string>}
     */
    public function import(int $limit = 50, bool $dryRun = false, bool $dispatchTranslations = true): array
    {
        $stats = ['total' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

        $offset = 0;

        while ($stats['imported'] < $limit) {
            $rows = $this->fetchPage($offset);
            if ($rows === null || \count($rows) === 0) {
                break;
            }

            foreach ($rows as $row) {
                if ($stats['imported'] >= $limit) {
                    break 2;
                }

                $stats['total']++;

                try {
                    $result = $this->processRow($row, $dryRun, $dispatchTranslations);
                    $stats[$result['status']]++;
                    if (!empty($result['message'])) {
                        $stats['details'][] = $result['message'];
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $stats['details'][] = \sprintf('Eroare la #%d: %s', $stats['total'], $e->getMessage());
                    $this->logger->error('Supabase import error', ['exception' => $e]);

                    if (!$this->em->isOpen()) {
                        $this->em = $this->managerRegistry->resetManager();
                        $this->categoryCache = [];
                        $this->authorCache = [];
                    }
                }

                if ($stats['imported'] > 0 && $stats['imported'] % self::BATCH_SIZE === 0 && !$dryRun) {
                    $this->em->flush();
                    $this->em->clear();
                    $this->categoryCache = [];
                    $this->authorCache = [];
                    $this->logger->info(\sprintf('Batch flush la %d articole importate', $stats['imported']));
                }
            }

            $offset += self::PAGE_SIZE;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        return $stats;
    }

    /**
     * Fetch a page of articles from Supabase REST API.
     *
     * @return list<array<string, mixed>>|null
     */
    private function fetchPage(int $offset): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::SUPABASE_URL . '/rest/v1/articles', [
                'timeout' => 30,
                'query' => [
                    'select' => self::SUPABASE_SELECT,
                    'draft' => 'eq.false',
                    'archived' => 'eq.false',
                    'order' => 'published_at.desc',
                    'offset' => (string) $offset,
                    'limit' => (string) self::PAGE_SIZE,
                ],
                'headers' => [
                    'apikey' => self::SUPABASE_ANON_KEY,
                    'Authorization' => 'Bearer ' . self::SUPABASE_ANON_KEY,
                ],
            ]);

            return $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Supabase page fetch failed', [
                'offset' => $offset,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array{status: 'imported'|'skipped'|'errors', message: string}
     */
    private function processRow(array $row, bool $dryRun, bool $dispatchTranslations): array
    {
        $externalId = (string) ($row['id'] ?? '');
        if ($externalId === '') {
            return ['status' => 'errors', 'message' => 'Row fără id — skip'];
        }

        if ($this->mappingRepository->existsByExternalId(self::SOURCE_NAME, $externalId)) {
            return ['status' => 'skipped', 'message' => ''];
        }

        $title = mb_substr(trim(strip_tags((string) ($row['title'] ?? ''))), 0, 255);
        if ($title === '') {
            return ['status' => 'errors', 'message' => 'Row fără titlu — skip'];
        }

        $content = (string) ($row['content_text'] ?? '');
        if ($content === '') {
            return ['status' => 'errors', 'message' => \sprintf('Skip (fără conținut): %s', $title)];
        }

        $lead = mb_substr(strip_tags((string) ($row['lead_text'] ?? '')), 0, 2000);

        $categoryName = trim((string) ($row['category'] ?? ''));
        if ($categoryName === '') {
            $categoryName = 'social';
        }

        $authorSlug = trim((string) ($row['author'] ?? ''));
        $authorName = $this->humanizeAuthorSlug($authorSlug !== '' ? $authorSlug : 'Redacția');

        $pubDate = new \DateTimeImmutable();
        $pubDateStr = (string) ($row['published_at'] ?? '');
        if ($pubDateStr !== '') {
            try {
                $pubDate = new \DateTimeImmutable($pubDateStr);
            } catch (\Throwable) {
                // keep default
            }
        }

        $imageUrl = (string) ($row['main_image'] ?? '');

        // Badge
        $badge = null;
        if (!empty($row['is_breaking_news'])) {
            $badge = ArticleBadge::BREAKING;
        } elseif (!empty($row['is_news_alert'])) {
            $badge = ArticleBadge::ALERT;
        } elseif (!empty($row['is_flash_news'])) {
            $badge = ArticleBadge::FLASH;
        }

        $isFeatured = !empty($row['is_featured']);

        $contentLen = mb_strlen(strip_tags($content));

        if ($dryRun) {
            return ['status' => 'imported', 'message' => \sprintf(
                '[DRY-RUN] %s | cat: %s | autor: %s | %d chars%s',
                $title,
                $categoryName,
                $authorName,
                $contentLen,
                $badge ? ' [' . $badge->value . ']' : '',
            )];
        }

        $category = $this->findOrCreateCategory($categoryName);
        $author = $this->findOrCreateAuthor($authorName);

        $article = new Article();
        $article->setTranslatableLocale('ro');
        $article->setTitle($title);
        $article->setLead($lead !== '' ? $lead : null);
        $article->setContent($content);
        $article->setStatus(ArticleStatus::PUBLISHED);
        $article->setPublishedAt($pubDate);
        $article->setCategory($category);
        $article->addAuthor($author);
        $article->setIsFeatured($isFeatured);

        if ($badge !== null) {
            $article->setBadge($badge);
        }

        $this->em->persist($article);

        if ($imageUrl !== '') {
            try {
                $this->downloadAndAttachImage($article, $imageUrl);
            } catch (\Throwable $e) {
                $this->logger->warning('Image download failed', [
                    'url' => $imageUrl,
                    'article' => $title,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Flush to get article ID
        $this->em->flush();

        // Save RU translations if available from Supabase
        $this->saveRuTranslations($article, $row);

        // External mapping for deduplication
        $mapping = new ExternalArticleMapping();
        $mapping->setSource(self::SOURCE_NAME);
        $mapping->setExternalId($externalId);
        $mapping->setArticle($article);
        $mapping->setMetadata([
            'original_slug' => (string) ($row['slug'] ?? ''),
            'original_category' => $categoryName,
            'original_author' => $authorSlug,
            'content_length' => $contentLen,
            'has_ru_translation' => !empty($row['title_ru']),
            'imported_at' => (new \DateTimeImmutable())->format('c'),
        ]);
        $this->em->persist($mapping);

        // Dispatch EN translation (RU is handled above from Supabase data)
        if ($dispatchTranslations && $article->getId() !== null) {
            $locales = !empty($row['title_ru']) ? ['en'] : ['ru', 'en'];
            $this->messageBus->dispatch(new TranslateArticleMessage(
                articleId: $article->getId(),
                locales: $locales,
                forceRetranslate: false,
            ));
        }

        return ['status' => 'imported', 'message' => \sprintf('OK: %s (%d chars)', $title, $contentLen)];
    }

    /**
     * Convert author slug ("iulian-chifu") to human name ("Iulian Chifu").
     */
    private function humanizeAuthorSlug(string $slug): string
    {
        if (!str_contains($slug, '-') || str_contains($slug, ' ')) {
            return $slug;
        }

        return mb_convert_case(str_replace('-', ' ', $slug), \MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Save Russian translations directly from Supabase data via Gedmo.
     *
     * @param array<string, mixed> $row
     */
    private function saveRuTranslations(Article $article, array $row): void
    {
        $titleRu = trim((string) ($row['title_ru'] ?? ''));
        if ($titleRu === '') {
            return;
        }

        /** @var TranslationRepository $translationRepo */
        $translationRepo = $this->em->getRepository('Gedmo\Translatable\Entity\Translation');

        $translationRepo->translate($article, 'title', 'ru', mb_substr($titleRu, 0, 255));

        $leadRu = trim((string) ($row['lead_text_ru'] ?? ''));
        if ($leadRu !== '') {
            $translationRepo->translate($article, 'lead', 'ru', mb_substr($leadRu, 0, 2000));
        }

        $contentRu = trim((string) ($row['content_text_ru'] ?? ''));
        if ($contentRu !== '') {
            $translationRepo->translate($article, 'content', 'ru', $contentRu);
        }

        $this->em->flush();
    }

    private function findOrCreateCategory(string $name): Category
    {
        $key = mb_strtolower($name);
        if (isset($this->categoryCache[$key])) {
            return $this->categoryCache[$key];
        }

        $resolvedName = self::CATEGORY_MAP[$key] ?? $name;

        $category = $this->categoryRepository->createQueryBuilder('c')
            ->where('LOWER(c.title) = LOWER(:title)')
            ->setParameter('title', $resolvedName)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($category === null) {
            $this->logger->warning('Import: unmapped category, creating new', ['category' => $name]);
            $category = new Category();
            $category->setTranslatableLocale('ro');
            $category->setTitle(ucfirst(mb_strtolower($name)));
            $category->setSlug($this->slugger->slug($name)->lower()->toString());
            $category->setStatus(CategoryStatus::ACTIVE);
            $this->em->persist($category);
            $this->em->flush();
        }

        $this->categoryCache[$key] = $category;

        return $category;
    }

    private function findOrCreateAuthor(string $fullName): Author
    {
        $key = mb_strtolower($fullName);
        if (isset($this->authorCache[$key])) {
            return $this->authorCache[$key];
        }

        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $emailSlug = $this->slugger->slug($fullName)->lower()->toString();
        $email = $emailSlug . '@imported.deschide.md';

        $author = $this->authorRepository->findOneBy(['email' => $email]);
        if ($author === null) {
            $author = new Author();
            $author->setFirstName($firstName);
            $author->setLastName($lastName);
            $author->setEmail($email);
            $author->setSlug($emailSlug);
            $this->em->persist($author);
            $this->em->flush();
        }

        $this->authorCache[$key] = $author;

        return $author;
    }

    private function downloadAndAttachImage(Article $article, string $imageUrl): void
    {
        $response = $this->httpClient->request('GET', $imageUrl, [
            'timeout' => 15,
            'max_redirects' => 3,
        ]);

        $contentType = $response->getHeaders()['content-type'][0] ?? 'image/jpeg';
        $extension = match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            default => 'jpg',
        };

        $imageContent = $response->getContent();
        $filename = \sprintf('rss_%s.%s', bin2hex(random_bytes(12)), $extension);
        $uploadDir = $this->projectDir . '/public/uploads/' . self::IMAGE_DIR;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        file_put_contents($uploadDir . '/' . $filename, $imageContent);

        $imageInfo = @getimagesize($uploadDir . '/' . $filename);
        $width = $imageInfo[0] ?? 0;
        $height = $imageInfo[1] ?? 0;

        $image = new Image();
        $image->setFilename($filename);
        $image->setPath(self::IMAGE_DIR . '/' . $filename);
        $image->setOriginalFilename(basename(parse_url($imageUrl, \PHP_URL_PATH) ?: $filename));
        $image->setMimeType($contentType);
        $image->setSize(\strlen($imageContent));
        $image->setWidth($width);
        $image->setHeight($height);
        $image->setAlt(mb_substr($article->getTitle(), 0, 255));
        $this->em->persist($image);

        $articleImage = new ArticleImage();
        $articleImage->setArticle($article);
        $articleImage->setImage($image);
        $articleImage->setPosition(0);
        $articleImage->setIsFeatured(true);
        $this->em->persist($articleImage);
    }
}
