<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\ExternalArticleMapping;
use App\Entity\Image;
use App\Enum\ArticleStatus;
use App\Enum\CategoryStatus;
use App\Message\TranslateArticleMessage;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use App\Repository\ExternalArticleMappingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Temporary RSS feed importer for deschide.md content population.
 *
 * Feed structure (RSS 2.0, no content:encoded — description only):
 *
 *  RSS Tag          → Symfony Field                    | Transform
 *  ──────────────────────────────────────────────────────────────────
 *  <title>          → Article.title                    | strip_tags, trim
 *  <guid>           → ExternalArticleMapping.externalId| deduplication key
 *  <description>    → Article.lead + Article.content   | lead=strip_tags; content=as-is
 *  <category>       → Category (findOrCreate)          | default: 'General'
 *  <author>         → Author (findOrCreate)            | default: 'Redacția'
 *  <pubDate>        → Article.publishedAt              | DateTimeImmutable
 *  <enclosure url>  → Image (download + attach)        | download to uploads/images/rss/
 *  <link>           → metadata.original_link           | stored for reference
 */
final class RssFeedImporter
{
    private const SOURCE_NAME = 'rss_deschide_md';
    private const FEED_URL = 'https://krjjgzewhghcdzckcjmb.supabase.co/functions/v1/rss';
    private const BATCH_SIZE = 20;
    private const IMAGE_DIR = 'images/rss';

    /** @var array<string, Category> */
    private array $categoryCache = [];

    /** @var array<string, Author> */
    private array $authorCache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em,
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

        $xml = $this->fetchFeed();
        if ($xml === null) {
            $stats['details'][] = 'EROARE: Nu am putut descărca/parsa feed-ul RSS.';
            return $stats;
        }

        $items = $xml->channel->item ?? [];
        foreach ($items as $item) {
            if ($stats['imported'] >= $limit) {
                break;
            }
            $stats['total']++;

            try {
                $result = $this->processItem($item, $dryRun, $dispatchTranslations);
                $stats[$result['status']]++;
                if (!empty($result['message'])) {
                    $stats['details'][] = $result['message'];
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                $stats['details'][] = \sprintf('Eroare la item #%d: %s', $stats['total'], $e->getMessage());
                $this->logger->error('RSS import error', ['exception' => $e]);
            }

            if ($stats['imported'] > 0 && $stats['imported'] % self::BATCH_SIZE === 0 && !$dryRun) {
                $this->em->flush();
                $this->em->clear();
                $this->categoryCache = [];
                $this->authorCache = [];
                $this->logger->info(\sprintf('Batch flush la %d articole importate', $stats['imported']));
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        return $stats;
    }

    private function fetchFeed(): ?\SimpleXMLElement
    {
        try {
            $response = $this->httpClient->request('GET', self::FEED_URL, [
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'application/rss+xml, application/xml, text/xml',
                    'User-Agent' => 'DeschideNewsApp/1.0 RSS Importer',
                ],
            ]);

            $content = $response->getContent();
            $content = ltrim($content, "\xEF\xBB\xBF");

            return new \SimpleXMLElement($content);
        } catch (\Throwable $e) {
            $this->logger->error('RSS feed fetch failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @return array{status: 'imported'|'skipped'|'errors', message: string}
     */
    private function processItem(\SimpleXMLElement $item, bool $dryRun, bool $dispatchTranslations): array
    {
        $guid = (string) ($item->guid ?? $item->link ?? '');
        if ($guid === '') {
            return ['status' => 'errors', 'message' => 'Item fără guid/link — skip'];
        }

        if ($this->mappingRepository->existsByExternalId(self::SOURCE_NAME, $guid)) {
            return ['status' => 'skipped', 'message' => ''];
        }

        $title = trim(strip_tags((string) $item->title));
        if ($title === '') {
            return ['status' => 'errors', 'message' => 'Item fără titlu — skip'];
        }

        $description = trim((string) ($item->description ?? ''));
        $lead = mb_substr(strip_tags($description), 0, 500);
        // No <content:encoded> in this feed — description is the only text
        $content = $description !== '' ? '<p>' . nl2br(htmlspecialchars(strip_tags($description), \ENT_QUOTES, 'UTF-8')) . '</p>' : '';

        $categoryName = trim((string) ($item->category ?? ''));
        if ($categoryName === '') {
            $categoryName = 'General';
        }

        $authorName = trim((string) ($item->author ?? ''));
        if ($authorName === '') {
            $authorName = 'Redacția';
        }

        $pubDate = null;
        $pubDateStr = (string) ($item->pubDate ?? '');
        if ($pubDateStr !== '') {
            try {
                $pubDate = new \DateTimeImmutable($pubDateStr);
            } catch (\Throwable) {
                $pubDate = new \DateTimeImmutable();
            }
        } else {
            $pubDate = new \DateTimeImmutable();
        }

        $imageUrl = '';
        if ($item->enclosure) {
            $enclosureType = (string) $item->enclosure['type'];
            if (str_starts_with($enclosureType, 'image/')) {
                $imageUrl = (string) $item->enclosure['url'];
            }
        }

        $originalLink = (string) ($item->link ?? '');

        if ($dryRun) {
            return ['status' => 'imported', 'message' => \sprintf('[DRY-RUN] %s | cat: %s | autor: %s', $title, $categoryName, $authorName)];
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

        // Flush to get article ID for mapping and translation dispatch
        $this->em->flush();

        $mapping = new ExternalArticleMapping();
        $mapping->setSource(self::SOURCE_NAME);
        $mapping->setExternalId($guid);
        $mapping->setArticle($article);
        $mapping->setMetadata([
            'original_link' => $originalLink,
            'original_category' => $categoryName,
            'original_author' => $authorName,
            'imported_at' => (new \DateTimeImmutable())->format('c'),
        ]);
        $this->em->persist($mapping);

        if ($dispatchTranslations && $article->getId() !== null) {
            $this->messageBus->dispatch(new TranslateArticleMessage(
                articleId: $article->getId(),
                locales: ['ru', 'en'],
                forceRetranslate: false,
            ));
        }

        return ['status' => 'imported', 'message' => \sprintf('OK: %s', $title)];
    }

    private function findOrCreateCategory(string $name): Category
    {
        $key = mb_strtolower($name);
        if (isset($this->categoryCache[$key])) {
            return $this->categoryCache[$key];
        }

        $category = $this->categoryRepository->createQueryBuilder('c')
            ->where('LOWER(c.title) = LOWER(:title)')
            ->setParameter('title', $name)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($category === null) {
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
